<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowVersion;
use App\WorkflowEngine\WorkflowExecutor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkflowEngineTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Tenant $tenant;
    private WorkflowExecutor $executor;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Setup Tenant and User
        $this->tenant = Tenant::create([
            'name' => 'Test Tenant',
            'domain' => 'test.localhost',
            'slug' => 'test-tenant',
        ]);
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
        
        $this->executor = $this->app->make(WorkflowExecutor::class);
    }

    public function test_can_execute_simple_http_workflow()
    {
        // 1. Create Workflow
        $workflow = Workflow::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test HTTP Workflow',
            'created_by' => $this->user->id,
        ]);

        // 2. Create Workflow Version with HTTP Node
        $definition = [
            'nodes' => [
                [
                    'id' => 'node-1',
                    'type' => 'http',
                    'data' => [
                        'url' => 'https://jsonplaceholder.typicode.com/posts/1',
                        'method' => 'GET',
                    ]
                ]
            ],
            'edges' => []
        ];

        $version = WorkflowVersion::create([
            'workflow_id' => $workflow->id,
            'version' => 1,
            'definition' => $definition,
            'is_published' => true,
            'created_by' => $this->user->id,
        ]);

        $workflow->update(['current_version_id' => $version->id]);

        // 3. Execute Workflow
        $run = $this->executor->execute($version, [], 'manual', $this->user->id);

        $this->assertNotNull($run);
        $this->assertEquals('running', $run->status);
        $this->assertEquals($this->tenant->id, $run->tenant_id);
    }

    public function test_can_execute_math_node_workflow()
    {
        // 1. Create Workflow
        $workflow = Workflow::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test Math Workflow',
            'created_by' => $this->user->id,
        ]);

        // 2. Create Workflow Version with Math Node
        $definition = [
            'nodes' => [
                [
                    'id' => 'node-1',
                    'type' => 'math',
                    'data' => [
                        'expression' => '10 + 5'
                    ]
                ]
            ],
            'edges' => []
        ];

        $version = WorkflowVersion::create([
            'workflow_id' => $workflow->id,
            'version' => 1,
            'definition' => $definition,
            'is_published' => true,
            'created_by' => $this->user->id,
        ]);

        // 3. Execute Workflow
        $run = $this->executor->execute($version, [], 'manual', $this->user->id);

        $this->assertNotNull($run);
        $this->assertEquals('running', $run->status);
    }
}
