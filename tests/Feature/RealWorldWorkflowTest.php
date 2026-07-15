<?php

namespace Tests\Feature;

use App\Jobs\ExecuteStepJob;
use App\Models\StepRun;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowVersion;
use App\WorkflowEngine\WorkflowExecutor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RealWorldWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $user;

    private WorkflowExecutor $executor;

    protected function setUp(): void
    {
        parent::setUp();

        // Ensure roles exist
        $this->artisan('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);

        $this->tenant = Tenant::create([
            'name' => 'Real World Corp',
            'slug' => 'real-world',
            'is_active' => true,
            'settings' => [],
        ]);

        $this->user = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Data Engineer',
            'email' => 'engineer@realworld.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
            'is_active' => true,
        ]);
        $this->user->assignRole('admin');

        setTenant($this->tenant);
        $this->executor = app(WorkflowExecutor::class);
    }

    public function test_real_world_data_processing_workflow(): void
    {
        Queue::fake();

        // Mock external API to simulate real-world data fetching
        Http::fake([
            'https://api.weather.gov/status' => Http::response(['status' => 'operational', 'temp' => 25], 200),
        ]);

        // Definition imitating the exact nodes in user's image: HTTP, Condition, Script, Delay, Notification
        $definition = [
            'nodes' => [
                [
                    'id' => 'fetch_data',
                    'type' => 'http',
                    'data' => [
                        'url' => 'https://api.weather.gov/status',
                        'method' => 'GET',
                        'timeout' => 5,
                    ],
                ],
                [
                    'id' => 'check_status',
                    'type' => 'condition',
                    'data' => [
                        'expression' => '{fetch_data.status} == 200',
                    ],
                ],
                [
                    'id' => 'process_data',
                    'type' => 'script', // Now acts as a safe math/expression evaluator
                    'data' => [
                        'code' => '{fetch_data.status} + 100',
                    ],
                ],
                [
                    'id' => 'wait_moment',
                    'type' => 'delay',
                    'data' => [
                        'seconds' => 1,
                    ],
                ],
                [
                    'id' => 'send_alert',
                    'type' => 'notification',
                    'data' => [
                        'message' => 'Processed Result: {process_data.result}',
                    ],
                ],
            ],
            'edges' => [
                ['source' => 'fetch_data', 'target' => 'check_status'],
                ['source' => 'check_status', 'target' => 'process_data'],
                ['source' => 'process_data', 'target' => 'wait_moment'],
                ['source' => 'wait_moment', 'target' => 'send_alert'],
            ],
        ];

        $workflow = Workflow::create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'name' => 'Weather Data Pipeline',
            'status' => 'active',
            'settings' => ['timeout_seconds' => 60],
        ]);

        $version = WorkflowVersion::create([
            'workflow_id' => $workflow->id,
            'version' => '1.0.0',
            'created_by' => $this->user->id,
            'definition' => $definition,
            'is_active' => true,
        ]);
        $workflow->update(['current_version_id' => $version->id]);

        // 1. Execute workflow (creates run and dispatches first step)
        $run = $this->executor->execute($version, [], 'manual', $this->user->id);
        $this->assertEquals('running', $run->status);

        // --- BATCH 0: HTTP NODE ---
        $fetchStep = StepRun::where('workflow_run_id', $run->id)->where('node_id', 'fetch_data')->first();
        $this->assertNotNull($fetchStep);
        (new ExecuteStepJob($fetchStep))->handle();

        $fetchStep->refresh();
        $this->assertEquals('completed', $fetchStep->status);
        $this->assertEquals(200, $fetchStep->output['status']);

        echo "\n\n--- HASIL NODE HTTP ---\n";
        echo 'Status Code: '.$fetchStep->output['status']."\n";
        echo 'Body Response: '.$fetchStep->output['body']."\n";
        $this->assertEquals(25, json_decode($fetchStep->output['body'], true)['temp']);

        // --- BATCH 1: CONDITION NODE ---
        $conditionStep = StepRun::where('workflow_run_id', $run->id)->where('node_id', 'check_status')->first();
        $this->assertNotNull($conditionStep);
        (new ExecuteStepJob($conditionStep))->handle();

        $conditionStep->refresh();
        if ($conditionStep->status === 'failed') {
            dump($conditionStep->error_message);
        }
        $this->assertEquals('completed', $conditionStep->status);
        // We expect it to evaluate to true since status was 200
        $this->assertTrue($conditionStep->output['condition_met']);

        echo "\n--- HASIL NODE CONDITION ---\n";
        echo "Kondisi '{fetch_data.status} == 200' terpenuhi? : ".($conditionStep->output['condition_met'] ? 'YA' : 'TIDAK')."\n";

        // --- BATCH 2: SCRIPT NODE ---
        $scriptStep = StepRun::where('workflow_run_id', $run->id)->where('node_id', 'process_data')->first();
        $this->assertNotNull($scriptStep);
        (new ExecuteStepJob($scriptStep))->handle();

        $scriptStep->refresh();
        $this->assertEquals('completed', $scriptStep->status);
        $this->assertEquals(300, $scriptStep->output['result']);

        echo "\n--- HASIL NODE SCRIPT ---\n";
        echo 'Hasil Eksekusi Script Aman (200 + 100): '.$scriptStep->output['result']."\n";

        // --- BATCH 3: DELAY NODE ---
        $delayStep = StepRun::where('workflow_run_id', $run->id)->where('node_id', 'wait_moment')->first();
        $this->assertNotNull($delayStep);
        (new ExecuteStepJob($delayStep))->handle();

        $delayStep->refresh();
        $this->assertEquals('completed', $delayStep->status);

        // --- BATCH 4: NOTIFICATION NODE ---
        $notifyStep = StepRun::where('workflow_run_id', $run->id)->where('node_id', 'send_alert')->first();
        $this->assertNotNull($notifyStep);
        (new ExecuteStepJob($notifyStep))->handle();

        $notifyStep->refresh();
        $this->assertEquals('completed', $notifyStep->status);
        $this->assertEquals('Processed Result: 300', $notifyStep->output['message']);

        // Assert workflow run is completed successfully
        $run->refresh();
        $this->assertEquals('completed', $run->status);
    }
}
