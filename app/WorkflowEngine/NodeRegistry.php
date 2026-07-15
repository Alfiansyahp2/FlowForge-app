<?php

namespace App\WorkflowEngine;

use App\WorkflowEngine\Contracts\ExecutableNodeInterface;
use Exception;

class NodeRegistry
{
    /**
     * @var array<string, ExecutableNodeInterface>
     */
    private array $executors = [];

    /**
     * Register a node executor.
     *
     * @param ExecutableNodeInterface $executor
     */
    public function register(ExecutableNodeInterface $executor): void
    {
        $this->executors[$executor->getType()] = $executor;
    }

    /**
     * Get an executor by node type.
     *
     * @param string $type
     * @return ExecutableNodeInterface
     * @throws Exception
     */
    public function getExecutor(string $type): ExecutableNodeInterface
    {
        if (!isset($this->executors[$type])) {
            throw new Exception("Unsupported node type: {$type}");
        }

        return $this->executors[$type];
    }
}
