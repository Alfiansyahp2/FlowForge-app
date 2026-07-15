<?php

namespace App\WorkflowEngine\Nodes;

use App\WorkflowEngine\Contracts\ExecutableNodeInterface;
use App\WorkflowEngine\Traits\VariableReplacerTrait;
use Illuminate\Support\Facades\Log;

class NotificationNodeExecutor implements ExecutableNodeInterface
{
    use VariableReplacerTrait;

    public function getType(): string
    {
        return 'notification';
    }

    public function execute(array $node, array &$context): array
    {
        $message = $this->replaceVariables($node['data']['message'] ?? '', $context['variables'] ?? []);

        // Log notification (in production, send to notification service)
        Log::info('Workflow Notification', ['message' => $message]);

        return [
            'sent' => true,
            'message' => $message,
        ];
    }
}
