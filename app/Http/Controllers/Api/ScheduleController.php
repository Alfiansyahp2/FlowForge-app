<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreScheduleRequest;
use App\Http\Requests\UpdateScheduleRequest;
use App\Http\Resources\ScheduleResource;
use App\Models\Schedule;
use App\Models\Workflow;
use App\WorkflowEngine\WorkflowExecutor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

class ScheduleController extends Controller
{
    private WorkflowExecutor $executor;

    public function __construct(WorkflowExecutor $executor)
    {
        $this->executor = $executor;
    }

    /**
     * Display a listing of schedules.
     */
    public function index(): AnonymousResourceCollection
    {
        $schedules = Schedule::with('workflow')
            ->latest()
            ->paginate(15);

        return ScheduleResource::collection($schedules);
    }

    /**
     * Store a newly created schedule in storage.
     *
     * @param  Request  $request
     */
    public function store(StoreScheduleRequest $request): ScheduleResource
    {

        // Get workflow
        $workflow = Workflow::findOrFail($request->input('workflow_id'));

        // Validate cron expression (basic validation)
        $cronExpression = $request->input('cron_expression');
        if (! $this->isValidCronExpression($cronExpression)) {
            throw ValidationException::withMessages([
                'cron_expression' => 'Cron expression must be in valid format (e.g., "* * * * *")',
            ]);
        }

        $schedule = Schedule::create([
            'tenant_id' => $workflow->tenant_id,
            'workflow_id' => $request->input('workflow_id'),
            'workflow_version_id' => $request->input('workflow_version_id'),
            'name' => $request->input('name'),
            'description' => $request->input('description'),
            'cron_expression' => $cronExpression,
            'timezone' => $request->input('timezone', config('app.timezone')),
            'is_active' => $request->input('is_active', true),
        ]);

        // Calculate next run time
        $schedule->updateNextRun();

        return new ScheduleResource($schedule);
    }

    /**
     * Display the specified schedule.
     */
    public function show(Schedule $schedule): ScheduleResource
    {
        $schedule->load(['workflow', 'workflowVersion']);

        return new ScheduleResource($schedule);
    }

    /**
     * Update the specified schedule in storage.
     *
     * @param  Request  $request
     */
    public function update(UpdateScheduleRequest $request, Schedule $schedule): ScheduleResource
    {

        // Validate cron expression if provided
        if ($request->has('cron_expression')) {
            $cronExpression = $request->input('cron_expression');
            if (! $this->isValidCronExpression($cronExpression)) {
                throw ValidationException::withMessages([
                    'cron_expression' => 'Cron expression must be in valid format (e.g., "* * * * *")',
                ]);
            }
        }

        $schedule->update($request->only([
            'name',
            'description',
            'cron_expression',
            'timezone',
            'is_active',
        ]));

        // Recalculate next run if cron expression changed
        if ($request->has('cron_expression')) {
            $schedule->updateNextRun();
        }

        return new ScheduleResource($schedule->fresh());
    }

    /**
     * Remove the specified schedule from storage.
     */
    public function destroy(Schedule $schedule): JsonResponse
    {
        $schedule->delete();

        return response()->json([
            'message' => 'Schedule deleted successfully',
        ]);
    }

    /**
     * Trigger a schedule immediately (manual trigger).
     */
    public function trigger(Schedule $schedule): JsonResponse
    {
        if (! $schedule->is_active) {
            return response()->json([
                'error' => 'Schedule is not active',
            ], 400);
        }

        // Get workflow
        $workflow = $schedule->workflow;

        // Determine which version to use
        $version = $schedule->workflowVersion ?? $workflow->currentVersion;

        if (! $version) {
            return response()->json([
                'error' => 'Workflow has no active version',
            ], 404);
        }

        // Execute workflow
        try {
            $input = [
                'schedule_id' => $schedule->id,
                'schedule_name' => $schedule->name,
                'triggered_at' => now()->toDateTimeString(),
                'trigger_type' => 'cron',
            ];

            $workflowRun = $this->executor->execute($version, $input);

            // Record the run
            $schedule->recordRun();

            return response()->json([
                'message' => 'Schedule triggered successfully',
                'workflow_run_id' => $workflowRun->id,
                'status' => $workflowRun->status,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Schedule execution failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Toggle schedule active status.
     */
    public function toggle(Schedule $schedule): ScheduleResource
    {
        $schedule->update([
            'is_active' => ! $schedule->is_active,
        ]);

        // If activating, update next run time
        if ($schedule->is_active && ! $schedule->next_run_at) {
            $schedule->updateNextRun();
        }

        return new ScheduleResource($schedule->fresh());
    }

    /**
     * Validate cron expression (basic validation).
     */
    private function isValidCronExpression(string $expression): bool
    {
        // Basic validation: must have 5 parts separated by spaces
        $parts = explode(' ', trim($expression));

        if (count($parts) !== 5) {
            return false;
        }

        // Each part should be valid (simplified validation)
        // minute hour day month day_of_week
        $validPatterns = [
            '/^[0-9*\/\-,]+$/',  // minute
            '/^[0-9*\/\-,]+$/',  // hour
            '/^[0-9*\/\-,LW?]+$/',  // day
            '/^[0-9*\/\-,]+$/',  // month
            '/^[0-9*\/\-,L?#]+$/',  // day_of_week
        ];

        foreach ($parts as $index => $part) {
            if (empty($part) && $part !== '0') {
                throw \Illuminate\Validation\ValidationException::withMessages(['cron_expression' => "Empty part at index $index"]);
            }

            if (! preg_match($validPatterns[$index], $part)) {
                throw \Illuminate\Validation\ValidationException::withMessages(['cron_expression' => "Regex failed at index $index for part '$part'"]);
            }
        }

        return true;
    }
}
