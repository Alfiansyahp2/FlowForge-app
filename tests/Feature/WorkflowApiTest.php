<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Models\Workflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Laravel\Sanctum\Sanctum;

class WorkflowApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->tenant = Tenant::create([
            'name' => 'Test Tenant',
            'domain' => 'test.localhost',
            'slug' => 'test-tenant',
        ]);
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
        
        $this->withoutMiddleware();
        Sanctum::actingAs($this->user, ['*']);
    }

    public function test_can_list_workflows()
    {
        Workflow::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'API Workflow 1',
            'created_by' => $this->user->id,
            'slug' => 'api-workflow-1'
        ]);

        $response = $this->getJson('/api/workflows');

        $response->assertStatus(200)
                 ->assertJsonCount(1, 'data')
                 ->assertJsonPath('data.0.name', 'API Workflow 1');
    }

    public function test_can_create_workflow()
    {
        $response = $this->postJson('/api/workflows', [
            'name' => 'New API Workflow',
            'description' => 'Test description'
        ]);

        $response->assertSuccessful()
                 ->assertJsonPath('data.name', 'New API Workflow');
                 
        $this->assertDatabaseHas('workflows', [
            'name' => 'New API Workflow',
            'tenant_id' => $this->tenant->id,
        ]);
    }
}
