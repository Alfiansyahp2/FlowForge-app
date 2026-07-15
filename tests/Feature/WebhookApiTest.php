<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Models\Workflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Laravel\Sanctum\Sanctum;

class WebhookApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Tenant $tenant;
    private Workflow $workflow;

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

        $this->withoutMiddleware();
        Sanctum::actingAs($this->user, ['*']);
    }

    public function test_can_create_webhook()
    {
        $response = $this->postJson('/api/webhooks', [
            'workflow_id' => $this->workflow->id,
            'name' => 'GitHub Push Webhook',
            'method' => 'POST'
        ]);

        $response->assertSuccessful()
                 ->assertJsonPath('data.name', 'GitHub Push Webhook');
                 
        $this->assertDatabaseHas('webhooks', [
            'name' => 'GitHub Push Webhook',
            'workflow_id' => $this->workflow->id,
        ]);
    }
}
