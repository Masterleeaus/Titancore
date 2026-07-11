<?php

namespace Modules\TitanCore\Services;

use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Log;
use Modules\TitanCore\AI\Providers\LocalModelProvider;
use Modules\TitanCore\AI\Providers\NullChatProvider;
use Modules\TitanCore\AI\Providers\NullEmbeddingProvider;
use Modules\TitanCore\AI\Providers\OpenAiChatProvider;
use Modules\TitanCore\AI\Providers\OpenAiEmbeddingProvider;
use Modules\TitanCore\Contracts\AI\ChatProviderContract;
use Modules\TitanCore\Contracts\AI\EmbeddingProviderContract;
use Modules\TitanCore\Services\UsageCostLogger;

/**
 * TitanCoreModelGateway
 *
 * Unified internal routing layer for all AI provider calls across the platform.
 *
 * Resolution order:
 * 1. Explicit provider key passed in $options['provider'].
 * 2. Default provider from config('titan_model_runtime.default').
 * 3. Failover chain when config('titan_model_runtime.failover.enabled') is true.
 *
 * Every call is logged with provider, model, token counts, and latency.
 */
class TitanCoreModelGateway
{
    public function __construct(
        protected ?UsageCostLogger $usageCostLogger = null,
        protected ?Container $container = null,
    ) {}

    /**
     * Route a chat completion request to the appropriate provider.
     *
     * @param  array  $messages  OpenAI-style messages array.
     * @param  array  $context   Runtime context: company_id, user_id, agent_slug, feature, provider, ...
     * @param  array  $options   Provider options: model, temperature, max_tokens, timeout, ...
     */
    public function chat(array $messages, array $context = [], array $options = []): array
    {
        $providerKey = $options['provider'] ?? ($context['provider'] ?? null);
        $provider    = $this->resolveChatProvider($providerKey);

        $result = $provider->chat($messages, $options);

        $this->logUsage('chat', $result, $context);

        return $result;
    }

    /**
     * Route an embedding request to the appropriate provider.
     *
     * @param  string|array  $input    Text or batch of texts to embed.
     * @param  array         $context  Runtime context.
     * @param  array         $options  Provider options: model, dimensions, timeout, ...
     */
    public function embed(string|array $input, array $context = [], array $options = []): array
    {
        $providerKey = $options['provider'] ?? ($context['provider'] ?? null);
        $provider    = $this->resolveEmbeddingProvider($providerKey);

        $result = $provider->embed($input, $options);

        $this->logUsage('embed', $result, $context);

        return $result;
    }

    /**
     * Check whether the currently configured primary provider is reachable.
     */
    public function health(string $providerKey = null): array
    {
        $chatProvider = $this->resolveChatProvider($providerKey);
        return $chatProvider->health();
    }

    // -------------------------------------------------------------------------
    // Provider resolution
    // -------------------------------------------------------------------------

    protected function resolveChatProvider(?string $key): ChatProviderContract
    {
        $key ??= config('titan_model_runtime.default', 'openai');

        if ($this->useFailoverChain($key)) {
            return $this->buildChatFailoverChain();
        }

        return $this->buildChatProvider($key);
    }

    protected function resolveEmbeddingProvider(?string $key): EmbeddingProviderContract
    {
        $key ??= config('titan_model_runtime.default_embedding', config('titan_model_runtime.default', 'openai'));

        if ($this->useFailoverChain($key)) {
            return $this->buildEmbeddingFailoverChain();
        }

        return $this->buildEmbeddingProvider($key);
    }

    protected function useFailoverChain(string $key): bool
    {
        return $key === 'failover'
            || (bool) config('titan_model_runtime.failover.enabled', false);
    }

    protected function buildChatProvider(string $key): ChatProviderContract
    {
        return match ($key) {
            'null'  => $this->resolveProvider(NullChatProvider::class),
            'local' => $this->resolveProvider(LocalModelProvider::class),
            default => $this->resolveProvider(OpenAiChatProvider::class), // 'openai' and unknown keys default to OpenAI
        };
    }

    protected function buildEmbeddingProvider(string $key): EmbeddingProviderContract
    {
        return match ($key) {
            'null'  => $this->resolveProvider(NullEmbeddingProvider::class),
            'local' => $this->resolveProvider(LocalModelProvider::class),
            default => $this->resolveProvider(OpenAiEmbeddingProvider::class),
        };
    }

    protected function buildChatFailoverChain(): ProviderFailoverChain
    {
        $list    = config('titan_model_runtime.failover.chat_providers', ['openai']);
        $statuses = config('titan_model_runtime.failover.on_statuses', [429, 500, 502, 503, 504]);

        $providers = array_map(
            fn (string $k) => $this->buildChatProvider($k),
            $list
        );

        return (new ProviderFailoverChain($providers))->setFailoverStatuses($statuses);
    }

    protected function buildEmbeddingFailoverChain(): ProviderFailoverChain
    {
        $list    = config('titan_model_runtime.failover.embedding_providers', ['openai']);
        $statuses = config('titan_model_runtime.failover.on_statuses', [429, 500, 502, 503, 504]);

        $providers = array_map(
            fn (string $k) => $this->buildEmbeddingProvider($k),
            $list
        );

        return (new ProviderFailoverChain($providers))->setFailoverStatuses($statuses);
    }

    /**
     * Resolve provider instances through the container when available.
     *
     * @param  class-string<ChatProviderContract|EmbeddingProviderContract>  $class
     * @return ChatProviderContract|EmbeddingProviderContract
     */
    protected function resolveProvider(string $class): ChatProviderContract|EmbeddingProviderContract
    {
        if ($this->container) {
            try {
                return $this->container->make($class);
            } catch (\Throwable $e) {
                throw new \RuntimeException(
                    sprintf('TitanCore failed to resolve AI provider [%s]: %s', $class, $e->getMessage()),
                    previous: $e,
                );
            }
        }

        return new $class();
    }

    // -------------------------------------------------------------------------
    // Usage logging
    // -------------------------------------------------------------------------

    protected function logUsage(string $feature, array $result, array $context): void
    {
        $provider   = $result['provider'] ?? 'unknown';
        $model      = $result['model'] ?? null;
        $latencyMs  = $result['latency_ms'] ?? 0;
        $usage      = $result['usage'] ?? null;

        Log::info('[TitanCore][Gateway] Provider call', [
            'feature'    => $feature,
            'provider'   => $provider,
            'model'      => $model,
            'ok'         => $result['ok'] ?? false,
            'latency_ms' => $latencyMs,
            'tokens'     => $usage,
        ]);

        // Detailed cost tracking via UsageCostLogger (best-effort)
        if ($this->usageCostLogger && $usage) {
            try {
                $this->usageCostLogger->logFromOpenAIResponse($feature, array_merge($result, [
                    'model'  => $model,
                ]), array_merge($context, [
                    'provider'   => $provider,
                    'latency_ms' => $latencyMs,
                ]));
            } catch (\Throwable $e) {
                Log::debug('[TitanCore][Gateway] Usage logging failed: ' . $e->getMessage());
            }
        }
    }
}
