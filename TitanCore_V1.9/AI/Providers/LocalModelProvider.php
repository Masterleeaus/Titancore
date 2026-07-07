<?php

namespace Modules\TitanCore\AI\Providers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\TitanCore\Contracts\AI\ChatProviderContract;
use Modules\TitanCore\Contracts\AI\EmbeddingProviderContract;

/**
 * LocalModelProvider
 *
 * Adapter for locally-hosted models exposed via an OpenAI-compatible HTTP API
 * (e.g. Ollama, llama.cpp server, LM Studio).
 *
 * Implements both ChatProviderContract and EmbeddingProviderContract so a single
 * instance can serve both use cases when the local endpoint supports both.
 */
class LocalModelProvider implements ChatProviderContract, EmbeddingProviderContract
{
    protected string $provider = 'local';

    public function __construct(
        protected string $baseUrl = '',
        protected string $defaultChatModel = '',
        protected string $defaultEmbedModel = '',
        protected int $timeoutSeconds = 60,
    ) {
        if (!$this->baseUrl) {
            $this->baseUrl = rtrim(
                (string) (config('titan_model_runtime.providers.local.base_url')
                    ?? env('LOCAL_MODEL_BASE_URL', 'http://localhost:11434')),
                '/'
            );
        }
        if (!$this->defaultChatModel) {
            $this->defaultChatModel = (string) (config('titan_model_runtime.providers.local.model')
                ?? env('LOCAL_MODEL_DEFAULT', 'llama3'));
        }
        if (!$this->defaultEmbedModel) {
            $this->defaultEmbedModel = (string) (config('titan_model_runtime.providers.local.embedding_model')
                ?? env('LOCAL_MODEL_EMBED', 'nomic-embed-text'));
        }
        $cfgTimeout = (int) (config('titan_model_runtime.providers.local.timeout_seconds') ?? 0);
        if ($cfgTimeout > 0) {
            $this->timeoutSeconds = $cfgTimeout;
        }
    }

    /**
     * {@inheritDoc}
     *
     * Ollama exposes an OpenAI-compatible /v1/chat/completions endpoint.
     */
    public function chat(array $messages, array $options = []): array
    {
        $startMs = (int) round(microtime(true) * 1000);
        $model   = (string) ($options['model'] ?? $this->defaultChatModel);
        $timeout = (int) ($options['timeout'] ?? $this->timeoutSeconds);

        $payload = [
            'model'    => $model,
            'messages' => $messages,
            'stream'   => false,
        ];
        if (isset($options['temperature'])) {
            $payload['temperature'] = (float) $options['temperature'];
        }
        if (isset($options['max_tokens'])) {
            $payload['max_tokens'] = (int) $options['max_tokens'];
        }

        try {
            $response = Http::timeout($timeout)
                ->post($this->baseUrl . '/v1/chat/completions', $payload);
        } catch (\Throwable $e) {
            Log::error('[TitanCore][LocalModelProvider] Chat HTTP error', [
                'error'    => $e->getMessage(),
                'model'    => $model,
                'base_url' => $this->baseUrl,
            ]);
            return $this->chatErrorResponse('HTTP request failed: ' . $e->getMessage(), $startMs);
        }

        $latencyMs = (int) round(microtime(true) * 1000) - $startMs;

        if (!$response->ok()) {
            $body     = $response->json() ?? [];
            $errorMsg = data_get($body, 'error.message', 'Local model error ' . $response->status());
            return [
                'ok'         => false,
                'content'    => null,
                'usage'      => null,
                'model'      => $model,
                'latency_ms' => $latencyMs,
                'provider'   => $this->provider,
                'error'      => $errorMsg,
                'status'     => $response->status(),
            ];
        }

        $body    = $response->json() ?? [];
        $content = (string) data_get($body, 'choices.0.message.content', '');
        $usage   = $body['usage'] ?? null;

        $normalizedUsage = null;
        if (is_array($usage)) {
            $normalizedUsage = [
                'prompt_tokens'     => (int) ($usage['prompt_tokens'] ?? 0),
                'completion_tokens' => (int) ($usage['completion_tokens'] ?? 0),
                'total_tokens'      => (int) ($usage['total_tokens'] ?? 0),
            ];
        }

        Log::debug('[TitanCore][LocalModelProvider] Chat success', [
            'model'      => $body['model'] ?? $model,
            'latency_ms' => $latencyMs,
        ]);

        return [
            'ok'         => true,
            'content'    => $content,
            'usage'      => $normalizedUsage,
            'model'      => $body['model'] ?? $model,
            'latency_ms' => $latencyMs,
            'provider'   => $this->provider,
            'error'      => null,
        ];
    }

    /**
     * {@inheritDoc}
     *
     * Ollama exposes an OpenAI-compatible /v1/embeddings endpoint.
     */
    public function embed(string|array $input, array $options = []): array
    {
        $startMs = (int) round(microtime(true) * 1000);
        $model   = (string) ($options['model'] ?? $this->defaultEmbedModel);
        $timeout = (int) ($options['timeout'] ?? $this->timeoutSeconds);

        $payload = [
            'model' => $model,
            'input' => $input,
        ];

        try {
            $response = Http::timeout($timeout)
                ->post($this->baseUrl . '/v1/embeddings', $payload);
        } catch (\Throwable $e) {
            Log::error('[TitanCore][LocalModelProvider] Embed HTTP error', [
                'error'    => $e->getMessage(),
                'model'    => $model,
                'base_url' => $this->baseUrl,
            ]);
            return $this->embedErrorResponse('HTTP request failed: ' . $e->getMessage(), $startMs);
        }

        $latencyMs = (int) round(microtime(true) * 1000) - $startMs;

        if (!$response->ok()) {
            $body     = $response->json() ?? [];
            $errorMsg = data_get($body, 'error.message', 'Local model error ' . $response->status());
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
     *
     * Pings the Ollama /api/tags or /v1/models endpoint to verify the server is up.
     */
    public function health(): array
    {
        if (!$this->baseUrl) {
            return ['ok' => false, 'provider' => $this->provider, 'reason' => 'LOCAL_MODEL_BASE_URL not configured'];
        }

        try {
            // Ollama uses /api/tags; OpenAI-compat servers use /v1/models — try both.
            $response = Http::timeout(5)->get($this->baseUrl . '/api/tags');
            if ($response->ok()) {
                return ['ok' => true, 'provider' => $this->provider, 'reason' => null];
            }

            $response = Http::timeout(5)->get($this->baseUrl . '/v1/models');
            if ($response->ok()) {
                return ['ok' => true, 'provider' => $this->provider, 'reason' => null];
            }

            return [
                'ok'       => false,
                'provider' => $this->provider,
                'reason'   => 'Server returned status ' . $response->status(),
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

    protected function chatErrorResponse(string $reason, int $startMs): array
    {
        return [
            'ok'         => false,
            'content'    => null,
            'usage'      => null,
            'model'      => null,
            'latency_ms' => (int) round(microtime(true) * 1000) - $startMs,
            'provider'   => $this->provider,
            'error'      => $reason,
        ];
    }

    protected function embedErrorResponse(string $reason, int $startMs): array
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
