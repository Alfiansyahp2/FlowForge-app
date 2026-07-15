<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowVersion;
use App\Models\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Laravel\Sanctum\Sanctum;

class ScheduleApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Tenant $tenant;
    private Workflow $workflow;
    private WorkflowVersion $version;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->tenant = Tenant::create([
            'name' => 'Test Tenant',
            'domain' => 'test.localhost',
            'slug' => 'test-tenant',
        ]);
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
        
        $this->workflow = Workflow::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'API Workflow',
            'created_by' => $this->user->id,
            'slug' => 'api-workflow'
        ]);

        $this->version = WorkflowVersion::create([
            'workflow_id' => $this->workflow->id,
            'version' => 1,
            'definition' => ['nodes' => [], 'edges' => []],
            'created_by' => $this->user->id,
        ]);

        $this->withoutMiddleware();
        Sanctum::actingAs($this->user, ['*']);
    }

    public function test_can_create_schedule()
    {
        $response = $this->postJson('/api/schedules', [
            'workflow_id' => $this->workflow->id,
            'workflow_version_id' => $this->version->id,
            'name' => 'Daily Run',
            'cron_expression' => '0 0 * * *',
            'timezone' => 'UTC'
        ]);

        $response->assertSuccessful()
                 ->assertJsonPath('data.name', 'Daily Run');
                 
        $this->assertDatabaseHas('schedules', [
            'name' => 'Daily Run',
            'workflow_id' => $this->workflow->id,
        ]);
    }
}
