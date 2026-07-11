<?php

namespace Modules\TitanCore\AI\Providers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\TitanCore\Contracts\AI\ChatProviderContract;

/**
 * OpenAiChatProvider
 *
 * Real chat completion adapter that calls the OpenAI /v1/chat/completions endpoint.
 * Response is normalised into the TitanCore ChatProviderContract shape.
 */
class OpenAiChatProvider implements ChatProviderContract
{
    protected string $provider = 'openai';

    public function __construct(
        protected string $apiKey = '',
        protected string $baseUrl = 'https://api.openai.com',
        protected string $defaultModel = 'gpt-4o-mini',
        protected float $defaultTemperature = 0.3,
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
        // Always prefer config over constructor default for model
        $cfgModel = (string) (config('titan_model_runtime.providers.openai.model')
            ?? config('ai.providers.openai.model')
            ?? '');
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
    public function chat(array $messages, array $options = []): array
    {
        $startMs = (int) round(microtime(true) * 1000);

        if (!$this->apiKey) {
            return $this->errorResponse('Missing OPENAI_API_KEY', $startMs);
        }

        $model       = (string) ($options['model'] ?? $this->defaultModel);
        $temperature = (float) ($options['temperature'] ?? $this->defaultTemperature);
        $maxTokens   = isset($options['max_tokens']) ? (int) $options['max_tokens'] : null;
        $timeout     = (int) ($options['timeout'] ?? $this->timeoutSeconds);

        $payload = [
            'model'       => $model,
            'messages'    => $messages,
            'temperature' => $temperature,
        ];
        if ($maxTokens !== null) {
            $payload['max_tokens'] = $maxTokens;
        }

        try {
            $response = Http::withToken($this->apiKey)
                ->timeout($timeout)
                ->post($this->baseUrl . '/v1/chat/completions', $payload);
        } catch (\Throwable $e) {
            Log::error('[TitanCore][OpenAiChatProvider] HTTP error', [
                'error' => $e->getMessage(),
                'model' => $model,
            ]);
            return $this->errorResponse('HTTP request failed: ' . $e->getMessage(), $startMs);
        }

        $latencyMs = (int) round(microtime(true) * 1000) - $startMs;

        if (!$response->ok()) {
            $body = $response->json() ?? [];
            $errorMsg = data_get($body, 'error.message', 'OpenAI error ' . $response->status());
            Log::warning('[TitanCore][OpenAiChatProvider] Non-OK response', [
                'status' => $response->status(),
                'model'  => $model,
                'error'  => $errorMsg,
            ]);
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

        Log::debug('[TitanCore][OpenAiChatProvider] Chat success', [
            'model'      => $body['model'] ?? $model,
            'tokens'     => $normalizedUsage,
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
            'content'    => null,
            'usage'      => null,
            'model'      => null,
            'latency_ms' => (int) round(microtime(true) * 1000) - $startMs,
            'provider'   => $this->provider,
            'error'      => $reason,
        ];
    }
}
