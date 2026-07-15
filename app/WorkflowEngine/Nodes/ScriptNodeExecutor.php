<?php

namespace App\WorkflowEngine\Nodes;

use App\WorkflowEngine\Contracts\ExecutableNodeInterface;
use App\WorkflowEngine\SafeExpressionEvaluator;
use Exception;

class ScriptNodeExecutor implements ExecutableNodeInterface
{
    private SafeExpressionEvaluator $evaluator;

    public function __construct(SafeExpressionEvaluator $evaluator)
    {
        $this->evaluator = $evaluator;
    }

    public function getType(): string
    {
        return 'script';
    }

    public function execute(array $node, array &$context): array
    {
        $code = $node['data']['code'] ?? '';

        try {
            $result = $this->evaluator->evaluate($code, $context['variables'] ?? []);

            return [
                'result' => $result,
                'status' => 'success',
            ];
        } catch (\Throwable $e) {
            throw new Exception('Script execution failed: '.$e->getMessage());
        }
    }
}
