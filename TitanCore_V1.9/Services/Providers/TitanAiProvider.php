<?php

namespace Modules\TitanCore\Services\Providers;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Modules\TitanCore\Services\titanaiClient;

class TitanAiProvider
{
    public function __construct(private readonly titanaiClient $client) {}

    public function isAllowedPath(string $path, array $allowedPrefixes): bool
    {
        $path = Str::startsWith($path, '/') ? $path : '/' . $path;
        foreach ($allowedPrefixes as $prefix) {
            $prefix = Str::startsWith($prefix, '/') ? $prefix : '/' . $prefix;
            if (Str::startsWith($path, $prefix)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Invoke a titanai capability.
     *
     * Two supported modes:
     * 1) tool invocation: ['tool' => 'chatbot'|'image'|..., 'input' => {...}]
     * 2) proxy invocation: ['method' => 'POST', 'path' => '/v1/...', 'payload' => {...}]
     */
    public function invoke(array $request, array $config): array
    {
        // Mode 2: proxy passthrough
        $path = Arr::get($request, 'path');
        if ($path) {
            $method = Arr::get($request, 'method', 'POST');
            $payload = Arr::get($request, 'payload', []);
            $headers = Arr::get($request, 'headers', []);
            $allowed = Arr::get($config, 'allowed_path_prefixes', ['/v1','/api']);
            if (!$this->isAllowedPath($path, $allowed)) {
                return ['ok' => false, 'status' => 403, 'body' => ['error' => 'Path not allowed', 'path' => $path]];
            }
            return $this->client->request($method, $path, is_array($payload) ? $payload : [], is_array($headers) ? $headers : []);
        }

        // Mode 1: opinionated tool invoke (configurable endpoint)
        $tool = Arr::get($request, 'tool');
        $input = Arr::get($request, 'input', []);
        $endpoint = Arr::get($config, 'tool_invoke_path', '/v1/tools/invoke');

        return $this->client->request('POST', $endpoint, [
            'tool' => $tool,
            'input' => $input,
        ]);
    }
}
