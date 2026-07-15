<?php

namespace App\WorkflowEngine\Nodes;

use App\WorkflowEngine\Contracts\ExecutableNodeInterface;
use App\WorkflowEngine\SafeExpressionEvaluator;
use Exception;
use Illuminate\Support\Facades\Log;

class MathNodeExecutor implements ExecutableNodeInterface
{
    private SafeExpressionEvaluator $evaluator;

    public function __construct(SafeExpressionEvaluator $evaluator)
    {
        $this->evaluator = $evaluator;
    }

    public function getType(): string
    {
        return 'math';
    }

    public function execute(array $node, array &$context): array
    {
        $expression = $node['data']['expression'] ?? '';

        try {
            $result = $this->evaluator->evaluate($expression, $context['variables'] ?? []);

            return [
                'result' => $result,
                'expression' => $expression,
            ];
        } catch (Exception $e) {
            Log::warning('Math expression evaluation failed', [
                'expression' => $expression,
                'error' => $e->getMessage(),
            ]);

            throw new Exception("Math expression failed: {$e->getMessage()}");
        }
    }
}
