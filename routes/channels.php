<?php

use App\Broadcasting\TenantChannel;
use App\Broadcasting\WorkflowChannel;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

// Workflow-specific channel for monitoring specific workflows
Broadcast::channel('workflows.{workflowId}', WorkflowChannel::class);

// Tenant-wide channel for tenant-level updates
Broadcast::channel('tenant.{tenantId}', TenantChannel::class);
