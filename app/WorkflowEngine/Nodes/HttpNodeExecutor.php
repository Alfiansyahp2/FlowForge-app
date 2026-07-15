<?php

namespace App\WorkflowEngine\Nodes;

use App\WorkflowEngine\Contracts\ExecutableNodeInterface;
use App\WorkflowEngine\Traits\VariableReplacerTrait;
use Illuminate\Support\Facades\Http;

class HttpNodeExecutor implements ExecutableNodeInterface
{
    use VariableReplacerTrait;

    public function getType(): string
    {
        return 'http';
    }

    public function execute(array $node, array &$context): array
    {
        $data = $node['data'] ?? [];
        $url = $this->replaceVariables($data['url'] ?? '', $context['variables'] ?? []);
        $method = strtoupper($data['method'] ?? 'GET');
        $headers = $data['headers'] ?? [];
        $body = $data['body'] ?? null;

        $response = Http::withHeaders($headers)
            ->timeout($data['timeout'] ?? 30)
            ->send($method, $url, $body ? ['body' => $body] : []);

        $response->throw();

        return [
            'status' => $response->status(),
            'headers' => $response->headers(),
            'body' => $response->body(),
        ];
    }
}
