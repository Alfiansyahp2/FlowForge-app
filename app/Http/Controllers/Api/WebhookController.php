<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\WebhookResource;
use App\Models\Webhook;
use App\Models\Workflow;
use App\WorkflowEngine\WorkflowExecutor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;

class WebhookController extends Controller
{
    private WorkflowExecutor $executor;

    public function __construct(WorkflowExecutor $executor)
    {
        $this->executor = $executor;
    }

    /**
     * Display a listing of webhooks.
     */
    public function index(): AnonymousResourceCollection
    {
        $webhooks = Webhook::with('workflow')->latest()->paginate(15);

        return WebhookResource::collection($webhooks);
    }

    /**
     * Store a newly created webhook in storage.
     */
    public function store(Request $request): WebhookResource
    {
        $request->validate([
            'workflow_id' => ['required', 'uuid'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
        ]);

        // Verify workflow exists and belongs to tenant (scopes automatically via TenantScope)
        Workflow::findOrFail($request->input('workflow_id'));

        // Generate unique webhook token
        $token = $this->generateUniqueToken();

        $webhook = Webhook::create([
            'tenant_id' => $request->user()?->tenant_id,
            'workflow_id' => $request->input('workflow_id'),
            'name' => $request->input('name'),
            'description' => $request->input('description'),
            'token' => $token,
            'is_active' => true,
            'last_triggered_at' => null,
        ]);

        return new WebhookResource($webhook);
    }

    /**
     * Display the specified webhook.
     */
    public function show(Webhook $webhook): WebhookResource
    {
        $webhook->load('workflow');

        return (new WebhookResource($webhook))->response()->setStatusCode(201);
    }

    /**
     * Update the specified webhook in storage.
     */
    public function update(Request $request, Webhook $webhook): WebhookResource
    {
        $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $webhook->update($request->only(['name', 'description', 'is_active']));

        return new WebhookResource($webhook->fresh());
    }

    /**
     * Remove the specified webhook from storage.
     */
    public function destroy(Webhook $webhook): JsonResponse
    {
        $webhook->delete();

        return response()->json([
            'message' => 'Webhook deleted successfully',
        ]);
    }

    /**
     * Handle incoming webhook trigger.
     */
    public function handleWebhook(string $token, Request $request): JsonResponse
    {
        // Find webhook by token
        $webhook = Webhook::where('token', $token)
            ->where('is_active', true)
            ->first();

        if (! $webhook) {
            return response()->json([
                'error' => 'Invalid webhook token',
            ], 404);
        }

        // Get workflow and current version
        $workflow = Workflow::with('currentVersion')->find($webhook->workflow_id);

        if (! $workflow || ! $workflow->currentVersion) {
            return response()->json([
                'error' => 'Workflow not found or has no active version',
            ], 404);
        }

        // Update webhook last triggered timestamp
        $webhook->update(['last_triggered_at' => now()]);

        // Execute workflow
        try {
            $input = [
                'webhook_token' => $token,
                'webhook_triggered_at' => now()->toDateTimeString(),
                'request_data' => $request->all(),
                'request_headers' => $request->headers->all(),
            ];

            $workflowRun = $this->executor->execute($workflow->currentVersion, $input);

            return response()->json([
                'message' => 'Workflow executed successfully',
                'workflow_run_id' => $workflowRun->id,
                'status' => $workflowRun->status,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Workflow execution failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Regenerate webhook token.
     */
    public function regenerateToken(Webhook $webhook): WebhookResource
    {
        $webhook->update([
            'token' => $this->generateUniqueToken(),
        ]);

        return new WebhookResource($webhook->fresh());
    }

    /**
     * Generate unique webhook token.
     */
    private function generateUniqueToken(): string
    {
        do {
            $token = Str::random(64);
        } while (Webhook::where('token', $token)->exists());

        return $token;
    }

    /**
     * Get webhook URL.
     */
    public function getUrl(Webhook $webhook): JsonResponse
    {
        $url = config('app.url').'/api/webhooks/'.$webhook->token;

        return response()->json([
            'url' => $url,
            'token' => $webhook->token,
        ]);
    }
}
