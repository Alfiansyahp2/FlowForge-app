<?php

namespace App\WorkflowEngine\Nodes;

use App\WorkflowEngine\Contracts\ExecutableNodeInterface;

class DelayNodeExecutor implements ExecutableNodeInterface
{
    public function getType(): string
    {
        return 'delay';
    }

    public function execute(array $node, array &$context): array
    {
        $seconds = $node['data']['seconds'] ?? 0;
        if ($seconds > 0) {
            sleep(min((int) $seconds, 60)); // Max 60 seconds delay for safety
        }

        return [
            'delayed_seconds' => $seconds,
        ];
    }
}
