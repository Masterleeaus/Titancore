<?php

namespace Modules\TitanCore\AI\Providers;

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
    protected const DISABLED_REASON = 'Direct OpenAI calls are disabled. Route chat requests through the TitanZero gateway.';

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
            'content'    => null,
            'usage'      => null,
            'model'      => null,
            'latency_ms' => (int) round(microtime(true) * 1000) - $startMs,
            'provider'   => $this->provider,
            'error'      => $reason,
        ];
    }
}
