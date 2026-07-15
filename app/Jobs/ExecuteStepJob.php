<?php

namespace App\Jobs;

use App\Events\StepCompleted;
use App\Events\StepStarted;
use App\Models\StepRun;
use App\WorkflowEngine\WorkflowExecutor;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\App;

class ExecuteStepJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public StepRun $stepRun;

    /**
     * Create a new job instance.
     */
    public function __construct(StepRun $stepRun)
    {
        $this->stepRun = $stepRun;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Reload model to get fresh status
        $this->stepRun->refresh();
        $stepRun = $this->stepRun;
        $workflowRun = $stepRun->workflowRun;

        // If workflow run is not running anymore, skip step
        if ($workflowRun->status !== 'running') {
            $stepRun->update(['status' => 'skipped']);

            return;
        }

        $workflowVersion = $workflowRun->version;
        $definition = $workflowVersion->definition;

        // Find node definition
        $node = collect($definition['nodes'] ?? [])->firstWhere('id', $stepRun->node_id);

        if (! $node) {
            $errorMsg = "Node {$stepRun->node_id} not found in definition";
            $stepRun->update(['status' => 'failed', 'error_message' => $errorMsg]);
            $workflowRun->update([
                'status' => 'failed',
                'error_message' => $errorMsg,
                'finished_at' => now(),
                'duration' => (int) $workflowRun->started_at->diffInMilliseconds(now()),
            ]);

            return;
        }

        $executor = App::make(WorkflowExecutor::class);

        // Build context with completed step runs output
        $completedSteps = StepRun::where('workflow_run_id', $workflowRun->id)
            ->where('status', 'completed')
            ->get();

        $variables = [];
        foreach ($completedSteps as $completedStep) {
            $variables[$completedStep->node_id] = $completedStep->output;
        }

        // Flatten variables using Arr::dot to support nested object/array lookups
        $dottedVariables = Arr::dot($variables);
        // Merge so both raw array variables and dot variables are available
        $mergedVariables = array_merge($variables, $dottedVariables);

        $context = [
            'workflow_run_id' => $workflowRun->id,
            'input' => $workflowRun->input ?? [],
            'variables' => $mergedVariables,
            'now' => $workflowRun->started_at ?? now(),
        ];

        $stepStartedAt = now();
        $stepRun->update([
            'status' => 'running',
            'started_at' => $stepStartedAt,
            'next_retry_at' => null, // Reset next retry
        ]);

        broadcast(new StepStarted($stepRun));

        try {
            // Execute the node
            $result = $executor->executeNode($node, $context);

            $stepFinishedAt = now();
            $stepRun->update([
                'status' => 'completed',
                'finished_at' => $stepFinishedAt,
                'duration' => (int) $stepStartedAt->diffInMilliseconds($stepFinishedAt),
                'output' => $result,
            ]);

            broadcast(new StepCompleted($stepRun));

            // Check if batch is completed and proceed to next batch
            $executor->checkAndProceedToNextBatch($workflowRun, $stepRun->sort_order);

        } catch (Exception $e) {
            $executor->handleStepFailure($stepRun, $e, $node);
        }
    }
}
