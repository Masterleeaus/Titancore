<?php

namespace Modules\TitanCore\AI\Providers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\TitanCore\Contracts\AI\EmbeddingProviderContract;

/**
 * OpenAiEmbeddingProvider
 *
 * Real embedding adapter that calls the OpenAI /v1/embeddings endpoint.
 * Response is normalised into the TitanCore EmbeddingProviderContract shape.
 */
class OpenAiEmbeddingProvider implements EmbeddingProviderContract
{
    protected string $provider = 'openai';

    public function __construct(
        protected string $apiKey = '',
        protected string $baseUrl = 'https://api.openai.com',
        protected string $defaultModel = 'text-embedding-3-small',
        protected int $timeoutSeconds = 30,
    ) {
        if (!$this->apiKey) {
            $this->apiKey = (string) (config('titan_model_runtime.providers.openai.api_key')
                ?? config('ai.providers.openai.api_key')
                ?? env('OPENAI_API_KEY', ''));
        }
        // Always prefer config over constructor default for base URL
        $cfgBase = (string) (config('titan_model_runtime.providers.openai.base_url')
            ?? config('ai.providers.openai.base')
            ?? '');
        if ($cfgBase) {
            $this->baseUrl = rtrim($cfgBase, '/');
        }
        // Always prefer config over constructor default for embedding model
        $cfgModel = (string) (config('titan_model_runtime.providers.openai.embedding_model') ?? '');
        if ($cfgModel) {
            $this->defaultModel = $cfgModel;
        }
        $cfgTimeout = (int) (config('titan_model_runtime.providers.openai.timeout_seconds') ?? 0);
        if ($cfgTimeout > 0) {
            $this->timeoutSeconds = $cfgTimeout;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function embed(string|array $input, array $options = []): array
    {
        $startMs = (int) round(microtime(true) * 1000);

        if (!$this->apiKey) {
            return $this->errorResponse('Missing OPENAI_API_KEY', $startMs);
        }

        $model   = (string) ($options['model'] ?? $this->defaultModel);
        $timeout = (int) ($options['timeout'] ?? $this->timeoutSeconds);

        $payload = [
            'model' => $model,
            'input' => $input,
        ];
        if (isset($options['dimensions'])) {
            $payload['dimensions'] = (int) $options['dimensions'];
        }

        try {
            $response = Http::withToken($this->apiKey)
                ->timeout($timeout)
                ->post($this->baseUrl . '/v1/embeddings', $payload);
        } catch (\Throwable $e) {
            Log::error('[TitanCore][OpenAiEmbeddingProvider] HTTP error', [
                'error' => $e->getMessage(),
                'model' => $model,
            ]);
            return $this->errorResponse('HTTP request failed: ' . $e->getMessage(), $startMs);
        }

        $latencyMs = (int) round(microtime(true) * 1000) - $startMs;

        if (!$response->ok()) {
            $body     = $response->json() ?? [];
            $errorMsg = data_get($body, 'error.message', 'OpenAI error ' . $response->status());
            Log::warning('[TitanCore][OpenAiEmbeddingProvider] Non-OK response', [
                'status' => $response->status(),
                'model'  => $model,
                'error'  => $errorMsg,
            ]);
            return [
                'ok'         => false,
                'vectors'    => null,
                'usage'      => null,
                'model'      => $model,
                'latency_ms' => $latencyMs,
                'provider'   => $this->provider,
                'error'      => $errorMsg,
                'status'     => $response->status(),
            ];
        }

        $body    = $response->json() ?? [];
        $data    = $body['data'] ?? [];
        $vectors = array_map(fn ($item) => $item['embedding'] ?? [], $data);
        $usage   = $body['usage'] ?? null;

        $normalizedUsage = null;
        if (is_array($usage)) {
            $normalizedUsage = [
                'prompt_tokens' => (int) ($usage['prompt_tokens'] ?? 0),
                'total_tokens'  => (int) ($usage['total_tokens'] ?? 0),
            ];
        }

        Log::debug('[TitanCore][OpenAiEmbeddingProvider] Embed success', [
            'model'      => $body['model'] ?? $model,
            'count'      => count($vectors),
            'tokens'     => $normalizedUsage,
            'latency_ms' => $latencyMs,
        ]);

        return [
            'ok'         => true,
            'vectors'    => $vectors,
            'usage'      => $normalizedUsage,
            'model'      => $body['model'] ?? $model,
            'latency_ms' => $latencyMs,
            'provider'   => $this->provider,
            'error'      => null,
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function health(): array
    {
        if (!$this->apiKey) {
            return ['ok' => false, 'provider' => $this->provider, 'reason' => 'Missing OPENAI_API_KEY'];
        }

        try {
            $response = Http::withToken($this->apiKey)
                ->timeout(10)
                ->get($this->baseUrl . '/v1/models');

            if ($response->ok()) {
                return ['ok' => true, 'provider' => $this->provider, 'reason' => null];
            }

            return [
                'ok'       => false,
                'provider' => $this->provider,
                'reason'   => 'API returned status ' . $response->status(),
            ];
        } catch (\Throwable $e) {
            return ['ok' => false, 'provider' => $this->provider, 'reason' => $e->getMessage()];
        }
    }

    /**
     * {@inheritDoc}
     */
    public function providerName(): string
    {
        return $this->provider;
    }

    protected function errorResponse(string $reason, int $startMs): array
    {
        return [
            'ok'         => false,
            'vectors'    => null,
            'usage'      => null,
            'model'      => null,
            'latency_ms' => (int) round(microtime(true) * 1000) - $startMs,
            'provider'   => $this->provider,
            'error'      => $reason,
        ];
    }
}
