<?php

namespace App\WorkflowEngine\Traits;

trait VariableReplacerTrait
{
    /**
     * Replace {{ variable.path }} syntax in a string with values from the context.
     *
     * @param string $text
     * @param array $variables
     * @return string
     */
    protected function replaceVariables(string $text, array $variables): string
    {
        return preg_replace_callback('/\{\{([^}]+)\}\}/', function ($matches) use ($variables) {
            $path = trim($matches[1]);
            return data_get($variables, $path, $matches[0]);
        }, $text);
    }
}
