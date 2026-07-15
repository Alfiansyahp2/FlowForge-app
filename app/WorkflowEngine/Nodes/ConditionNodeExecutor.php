<?php

namespace App\WorkflowEngine\Nodes;

use App\WorkflowEngine\Contracts\ExecutableNodeInterface;
use App\WorkflowEngine\SafeExpressionEvaluator;

class ConditionNodeExecutor implements ExecutableNodeInterface
{
    private SafeExpressionEvaluator $evaluator;

    public function __construct(SafeExpressionEvaluator $evaluator)
    {
        $this->evaluator = $evaluator;
    }

    public function getType(): string
    {
        return 'condition';
    }

    public function execute(array $node, array &$context): array
    {
        $expression = $node['data']['expression'] ?? '';

        $result = $this->evaluator->evaluate($expression, $context['variables'] ?? []);

        return [
            'condition_met' => (bool)$result,
            'expression' => $expression,
        ];
    }
}
