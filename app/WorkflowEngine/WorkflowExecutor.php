<?php

namespace App\WorkflowEngine;

use App\Events\StepFailed;
use App\Events\WorkflowCompleted;
use App\Events\WorkflowFailed;
use App\Events\WorkflowStarted;
use App\Jobs\ExecuteStepJob;
use App\Jobs\RetryStepJob;
use App\Models\StepRun;
use App\Models\WorkflowRun;
use App\Models\WorkflowVersion;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WorkflowExecutor
{
    private WorkflowValidator $validator;

    private TopologicalSorter $sorter;

    private RetryManager $retryManager;

    private NodeRegistry $nodeRegistry;

    public function __construct(
        WorkflowValidator $validator,
        TopologicalSorter $sorter,
        RetryManager $retryManager,
        ?NodeRegistry $nodeRegistry = null
    ) {
        $this->validator = $validator;
        $this->sorter = $sorter;
        $this->retryManager = $retryManager;
        $this->nodeRegistry = $nodeRegistry ?? app(NodeRegistry::class);
    }

    /**
     * Execute a workflow.
     *
     * @param  string  $triggerType  manual | webhook | cron
     * @param  string|null  $triggeredBy  User ID (for manual runs)
     *
     * @throws Exception
     */
    public function execute(
        WorkflowVersion $version,
        array $input = [],
        string $triggerType = 'manual',
        ?string $triggeredBy = null
    ): WorkflowRun {
        $definition = $version->definition;

        // Validate workflow
        $this->validator->validateOrFail($definition);

        $now = now();
        $timeoutSeconds = $version->workflow->settings['timeout_seconds'] ?? 1800; // default 30 mins

        // Create workflow run
        $workflowRun = WorkflowRun::create([
            'tenant_id' => $version->workflow->tenant_id,
            'workflow_id' => $version->workflow->id,
            'workflow_version_id' => $version->id,
            'trigger_type' => $triggerType,
            'triggered_by' => $triggeredBy,
            'status' => 'running',
            'input' => $input,
            'started_at' => $now,
            'timeout_seconds' => $timeoutSeconds,
            'timeout_at' => $now->copy()->addSeconds($timeoutSeconds),
        ]);

        // Broadcast workflow started event
        broadcast(new WorkflowStarted($workflowRun));

        // Start execution of Batch 0
        $this->startBatch($workflowRun, 0);

        return $workflowRun;
    }

    /**
     * Start execution of a batch.
     */
    public function startBatch(WorkflowRun $workflowRun, int $batchIndex): void
    {
        $workflowRun->refresh();

        if ($workflowRun->status !== 'running') {
            return;
        }

        $definition = $workflowRun->version->definition;
        $executionBatches = $this->sorter->getExecutionBatches($definition);

        // If no more batches, complete the workflow run
        if ($batchIndex >= count($executionBatches)) {
            $finishedAt = now();
            $duration = max(0, $finishedAt->diffInMilliseconds($workflowRun->started_at));

            $workflowRun->update([
                'status' => 'completed',
                'finished_at' => $finishedAt,
                'duration' => $duration,
            ]);

            broadcast(new WorkflowCompleted($workflowRun));

            return;
        }

        $batch = $executionBatches[$batchIndex];

        foreach ($batch['nodes'] as $nodePosition => $nodeId) {
            $node = $this->findNode($definition, $nodeId);
            if (! $node) {
                continue;
            }

            // sort_order = batchIndex * 100 + position within batch
            $sortOrder = ($batchIndex * 100) + $nodePosition;

            $stepRun = $this->createStepRun(
                $workflowRun->id,
                $nodeId,
                $node,
                $sortOrder,
                $workflowRun->input ?? []
            );

            // Dispatch as background job
            ExecuteStepJob::dispatch($stepRun);
        }
    }

    /**
     * Check if current batch is completed and proceed to next one safely.
     */
    public function checkAndProceedToNextBatch(WorkflowRun $workflowRun, int $currentSortOrder): void
    {
        $batchIndex = (int) floor($currentSortOrder / 100);

        DB::transaction(function () use ($workflowRun, $batchIndex) {
            // Lock workflow run to prevent race conditions
            $run = WorkflowRun::where('id', $workflowRun->id)->lockForUpdate()->first();

            if ($run->status !== 'running') {
                return;
            }

            $definition = $run->version->definition;
            $executionBatches = $this->sorter->getExecutionBatches($definition);

            if (! isset($executionBatches[$batchIndex])) {
                return;
            }

            $batch = $executionBatches[$batchIndex];

            // Check if all steps in current batch are completed
            $unfinished = StepRun::where('workflow_run_id', $run->id)
                ->whereBetween('sort_order', [$batchIndex * 100, $batchIndex * 100 + 99])
                ->where('status', '!=', 'completed')
                ->exists();

            if (! $unfinished) {
                // Check if next batch was already started
                $nextBatchIndex = $batchIndex + 1;
                $nextBatchStarted = StepRun::where('workflow_run_id', $run->id)
                    ->whereBetween('sort_order', [$nextBatchIndex * 100, $nextBatchIndex * 100 + 99])
                    ->exists();

                if (! $nextBatchStarted) {
                    $this->startBatch($run, $nextBatchIndex);
                }
            }
        });
    }

    /**
     * Execute a single node.
     *
     * @throws Exception
     */
    public function executeNode(array $node, array &$context): array
    {
        $nodeType = $node['type'];
        $executor = $this->nodeRegistry->getExecutor($nodeType);

        return $executor->execute($node, $context);
    }

    /**
     * Handle step execution failure.
     */
    public function handleStepFailure(StepRun $stepRun, Exception $exception, array $node): void
    {
        $maxRetries = $node['data']['max_retries'] ?? 3;
        $retryDelay = $node['data']['retry_delay'] ?? 5;

        $finishedAt = now();
        $duration = max(0, $finishedAt->diffInMilliseconds($stepRun->started_at ?? now()));

        $stepRun->update([
            'status' => 'failed',
            'finished_at' => $finishedAt,
            'duration' => $duration,
            'error_message' => $exception->getMessage(),
        ]);

        // Broadcast step failed event
        broadcast(new StepFailed($stepRun, $exception->getMessage()));

        // Check if we should retry
        if ($this->retryManager->shouldRetry($stepRun->retry_count, $maxRetries)) {
            $stepRun->increment('retry_count');
            $delay = $this->retryManager->calculateDelay($stepRun->retry_count, $retryDelay);
            $stepRun->update(['next_retry_at' => now()->addSeconds($delay)]);

            // Queue a retry job for the workflow run
            RetryStepJob::dispatch($stepRun)->delay(now()->addSeconds($delay));

            Log::info("Step {$stepRun->node_id} failed, scheduled retry in {$delay}s", [
                'workflow_run_id' => $stepRun->workflow_run_id,
                'step_run_id' => $stepRun->id,
                'retry_count' => $stepRun->retry_count,
                'next_retry_at' => $stepRun->next_retry_at,
            ]);
        } else {
            // No retries left: Mark the entire workflow run as failed
            $workflowRun = $stepRun->workflowRun;
            $workflowRun->update([
                'status' => 'failed',
                'finished_at' => now(),
                'error_message' => "Step {$stepRun->node_id} failed: ".$exception->getMessage(),
                'duration' => (int) $workflowRun->started_at->diffInMilliseconds(now()),
            ]);

            broadcast(new WorkflowFailed($workflowRun, $exception->getMessage()));
        }
    }

    /**
     * Create a step run record.
     */
    private function createStepRun(string $workflowRunId, string $nodeId, array $node, int $sortOrder, array $input): StepRun
    {
        return StepRun::create([
            'workflow_run_id' => $workflowRunId,
            'node_id' => $nodeId,
            'node_type' => $node['type'],
            'status' => 'pending',
            'sort_order' => $sortOrder,
            'input' => $input,
            'retry_config' => [
                'max_retries' => $node['data']['max_retries'] ?? 3,
                'retry_delay' => $node['data']['retry_delay'] ?? 5,
            ],
        ]);
    }

    /**
     * Find node by ID in definition.
     */
    private function findNode(array $definition, string $nodeId): ?array
    {
        foreach ($definition['nodes'] ?? [] as $node) {
            if ($node['id'] === $nodeId) {
                return $node;
            }
        }

        return null;
    }
}
