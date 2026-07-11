<?php

namespace Modules\TitanCore\AI\Adapters;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\TitanCore\AI\ClientInterface;
use Modules\TitanCore\Services\UsageLogger;

class OpenAIClient implements ClientInterface
{
    protected const CHAT_ENDPOINT = 'https://api.openai.com/v1/chat/completions';
    protected const EMBEDDINGS_ENDPOINT = 'https://api.openai.com/v1/embeddings';

    protected string $apiKey;
    protected string $model;
    protected string $embeddingModel;
    protected string $provider = 'openai';

    public function __construct()
    {
        $this->apiKey = (string) (config('ai.providers.openai.api_key') ?? env('OPENAI_API_KEY', ''));
        $this->model = (string) config('ai.providers.openai.model', 'gpt-4o-mini');
        $this->embeddingModel = (string) config('ai.providers.openai.embedding_model', 'text-embedding-3-small');
    }

    public function chat(array $messages, array $opts = []): array
    {
        if (!$this->apiKey) {
            return ['ok' => false, 'content' => null, 'usage' => null, 'reason' => 'Missing OPENAI_API_KEY'];
        }

        $payload = [
            'model' => $opts['model'] ?? $this->model,
            'messages' => $messages,
        ];

        foreach (['temperature', 'max_tokens', 'top_p', 'presence_penalty', 'frequency_penalty', 'tools', 'tool_choice'] as $key) {
            if (array_key_exists($key, $opts)) {
                $payload[$key] = $opts[$key];
            }
        }

        if (!empty($opts['stream'])) {
            $payload['stream'] = true;
        }

        try {
            $response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ])
                ->post(self::CHAT_ENDPOINT, $payload);

            if ($response->failed()) {
                return ['ok' => false, 'content' => null, 'usage' => null, 'reason' => 'HTTP ' . $response->status()];
            }

            $json = $response->json();

            $usage = [
                'prompt_tokens' => (int) ($json['usage']['prompt_tokens'] ?? 0),
                'completion_tokens' => (int) ($json['usage']['completion_tokens'] ?? 0),
                'total_tokens' => (int) ($json['usage']['total_tokens'] ?? 0),
            ];

            $this->logUsage($usage['total_tokens']);

            return [
                'ok' => true,
                'content' => $json['choices'][0]['message']['content'] ?? null,
                'usage' => $usage,
                'reason' => null,
            ];
        } catch (\Throwable $e) {
            Log::error('OpenAIClient: exception during chat.', ['error' => $e->getMessage()]);

            return ['ok' => false, 'content' => null, 'usage' => null, 'reason' => $e->getMessage()];
        }
    }

    public function embed(array $input, array $opts = []): array
    {
        if (!$this->apiKey) {
            return ['ok' => false, 'vector' => null, 'reason' => 'Missing OPENAI_API_KEY'];
        }

        $payload = [
            'model' => $opts['model'] ?? $this->embeddingModel,
            'input' => $input,
        ];

        if (array_key_exists('dimensions', $opts)) {
            $payload['dimensions'] = $opts['dimensions'];
        }

        try {
            $response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ])
                ->post(self::EMBEDDINGS_ENDPOINT, $payload);

            if ($response->failed()) {
                return ['ok' => false, 'vector' => null, 'reason' => 'HTTP ' . $response->status()];
            }

            $json = $response->json();

            $usage = [
                'prompt_tokens' => (int) ($json['usage']['prompt_tokens'] ?? 0),
                'total_tokens' => (int) ($json['usage']['total_tokens'] ?? 0),
            ];

            $tokens = $json['usage']['total_tokens'] ?? $json['usage']['prompt_tokens'] ?? 0;
            $this->logUsage((int) $tokens);

            return [
                'ok' => true,
                'vector' => $json['data'][0]['embedding'] ?? null,
                'usage' => $usage,
                'reason' => null,
                'data' => $json['data'] ?? [],
            ];
        } catch (\Throwable $e) {
            Log::error('OpenAIClient: exception during embeddings.', ['error' => $e->getMessage()]);

            return ['ok' => false, 'vector' => null, 'reason' => $e->getMessage()];
        }
    }

    public function health(): array
    {
        return ['ok' => (bool)$this->apiKey, 'provider' => $this->provider, 'reason' => $this->apiKey ? null : 'Missing API key'];
    }

    protected function logUsage(int $tokens): void
    {
        $tenantId = null;

        if (function_exists('auth')) {
            try {
                $user = auth()->user();
                $tenantId = $user->tenant_id ?? null;
            } catch (\Throwable) {
                $tenantId = null;
            }
        }

        $key = $tenantId ? ('tenant:' . $tenantId) : 'global';

        try {
            UsageLogger::add($key, max(0, $tokens), 1);
        } catch (\Throwable $e) {
            Log::debug('OpenAIClient: usage logging skipped.', ['error' => $e->getMessage()]);
        }
    }
}
