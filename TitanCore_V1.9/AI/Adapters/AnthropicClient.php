<?php

namespace Modules\TitanCore\AI\Adapters;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\TitanCore\AI\ClientInterface;
use Modules\TitanCore\Services\UsageLogger;

class AnthropicClient implements ClientInterface
{
    protected const ENDPOINT          = 'https://api.anthropic.com/v1/messages';
    protected const EMBEDDINGS_ENDPOINT = 'https://api.openai.com/v1/embeddings';
    protected const ANTHROPIC_VERSION = '2023-06-01';

    protected string $apiKey;
    protected string $openAiApiKey;
    protected string $model;
    protected string $embeddingModel;
    protected string $provider = 'anthropic';

    public function __construct()
    {
        $this->apiKey = (string) (config('ai.providers.anthropic.api_key') ?? env('ANTHROPIC_API_KEY', ''));
        $this->openAiApiKey = (string) (config('ai.providers.openai.api_key') ?? env('OPENAI_API_KEY', ''));
        $this->model  = (string) config('ai.providers.anthropic.model', 'claude-3-haiku-20240307');
        $this->embeddingModel = (string) config('ai.providers.anthropic.embedding_model', 'text-embedding-3-small');
    }

    public function chat(array $messages, array $opts = []): array
    {
        if (!$this->apiKey) {
            return ['ok' => false, 'content' => null, 'usage' => null, 'reason' => 'Missing ANTHROPIC_API_KEY'];
        }

        // Separate system prompt from conversation messages (Anthropic-specific format)
        $system   = null;
        $filtered = [];
        foreach ($messages as $msg) {
            if (($msg['role'] ?? '') === 'system') {
                $system = $msg['content'];
            } else {
                $filtered[] = $msg;
            }
        }

        $maxTokens = $opts['max_tokens'] ?? 4096;
        $model     = $opts['model'] ?? $this->model;

        $payload = [
            'model'      => $model,
            'max_tokens' => $maxTokens,
            'messages'   => $filtered,
        ];

        if ($system !== null) {
            $payload['system'] = $system;
        }

        if (!empty($opts['tools'])) {
            $payload['tools'] = $opts['tools'];
        }

        if (!empty($opts['stream'])) {
            $payload['stream'] = true;
        }

        try {
            $response = Http::withHeaders([
                'x-api-key'         => $this->apiKey,
                'anthropic-version' => self::ANTHROPIC_VERSION,
                'Content-Type'      => 'application/json',
                'Accept'            => 'application/json',
            ])->post(self::ENDPOINT, $payload);

            if ($response->failed()) {
                Log::error('AnthropicClient: request failed.', ['status' => $response->status()]);
                return ['ok' => false, 'content' => null, 'usage' => null, 'reason' => 'HTTP ' . $response->status()];
            }

            $json = $response->json();

            // Extract first text content block
            $content = null;
            foreach ($json['content'] ?? [] as $block) {
                if (($block['type'] ?? '') === 'text') {
                    $content = $block['text'];
                    break;
                }
            }

            $inputTokens  = (int) ($json['usage']['input_tokens'] ?? 0);
            $outputTokens = (int) ($json['usage']['output_tokens'] ?? 0);
            $this->logUsage($inputTokens + $outputTokens);

            return [
                'ok'      => true,
                'content' => $content,
                'usage'   => [
                    'prompt_tokens'     => $inputTokens,
                    'completion_tokens' => $outputTokens,
                    'total_tokens'      => $inputTokens + $outputTokens,
                ],
                'reason'  => null,
            ];
        } catch (\Throwable $e) {
            Log::error('AnthropicClient: exception during chat.', ['error' => $e->getMessage()]);
            return ['ok' => false, 'content' => null, 'usage' => null, 'reason' => $e->getMessage()];
        }
    }

    public function embed(array $input, array $opts = []): array
    {
        if (!$this->openAiApiKey) {
            return ['ok' => false, 'vector' => null, 'reason' => 'Anthropic does not support embeddings and OPENAI_API_KEY is missing'];
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
                    'Authorization' => 'Bearer ' . $this->openAiApiKey,
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
            Log::error('AnthropicClient: exception during embeddings fallback.', ['error' => $e->getMessage()]);

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
            Log::debug('AnthropicClient: usage logging skipped.', ['error' => $e->getMessage()]);
        }
    }
}
