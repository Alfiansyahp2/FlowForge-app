<?php

namespace App\WorkflowEngine\Contracts;

interface ExecutableNodeInterface
{
    /**
     * Get the type identifier for this node (e.g., 'http', 'delay').
     *
     * @return string
     */
    public function getType(): string;

    /**
     * Execute the node logic.
     *
     * @param array $node The full node data including its config
     * @param array $context The execution context (variables, etc.)
     * @return array Output of the node execution
     * @throws \Exception
     */
    public function execute(array $node, array &$context): array;
}
