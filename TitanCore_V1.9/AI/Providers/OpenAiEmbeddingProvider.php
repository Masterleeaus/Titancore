<?php

namespace Modules\TitanCore\AI\Providers;

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
    protected const DISABLED_REASON = 'Direct OpenAI calls are disabled. Route embedding requests through the TitanZero gateway.';

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

        return $this->errorResponse(self::DISABLED_REASON, $startMs);
    }

    /**
     * {@inheritDoc}
     */
    public function health(): array
    {
        if (!$this->apiKey) {
            return ['ok' => false, 'provider' => $this->provider, 'reason' => 'Missing OPENAI_API_KEY'];
        }

        return ['ok' => false, 'provider' => $this->provider, 'reason' => self::DISABLED_REASON];
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
