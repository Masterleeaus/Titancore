<?php

namespace Modules\TitanCore\Services;

use Illuminate\Support\Facades\Log;
use Modules\TitanCore\Contracts\AI\ChatProviderContract;
use Modules\TitanCore\Contracts\AI\EmbeddingProviderContract;

/**
 * ProviderFailoverChain
 *
 * Wraps an ordered list of ChatProviderContract (or EmbeddingProviderContract)
 * instances and automatically fails over to the next provider when the primary
 * returns an error or an HTTP status that warrants a retry (429, 500–599).
 *
 * Usage:
 *   $chain = new ProviderFailoverChain([$primary, $secondary]);
 *   $result = $chain->chat($messages);
 */
class ProviderFailoverChain implements ChatProviderContract, EmbeddingProviderContract
{
    /** HTTP status codes that trigger failover to the next provider. */
    protected array $failoverStatuses = [429, 500, 502, 503, 504];

    /**
     * @param  ChatProviderContract[]|EmbeddingProviderContract[]  $providers
     *         Ordered list of providers; first entry is primary.
     */
    public function __construct(protected array $providers = []) {}

    /**
     * Try each provider in order for chat completions.
     * Falls over to the next provider on error or failover-eligible HTTP status.
     *
     * {@inheritDoc}
     */
    public function chat(array $messages, array $options = []): array
    {
        $lastResult = ['ok' => false, 'content' => null, 'error' => 'No chat providers configured in failover chain'];

        foreach ($this->providers as $provider) {
            if (!$provider instanceof ChatProviderContract) {
                continue;
            }

            $result = $provider->chat($messages, $options);

            if ($result['ok'] ?? false) {
                return $result;
            }

            $status = $result['status'] ?? null;
            $shouldFailover = !isset($status) || in_array((int) $status, $this->failoverStatuses, true);

            Log::warning('[TitanCore][ProviderFailoverChain] Chat provider failed', [
                'provider'   => $provider->providerName(),
                'status'     => $status,
                'error'      => $result['error'] ?? null,
                'failover'   => $shouldFailover,
            ]);

            $lastResult = $result;

            if (!$shouldFailover) {
                // Non-retryable error (e.g. 400 bad request) — stop chain.
                break;
            }
        }

        return $lastResult;
    }

    /**
     * Try each provider in order for embeddings.
     * Falls over to the next provider on error or failover-eligible HTTP status.
     *
     * {@inheritDoc}
     */
    public function embed(string|array $input, array $options = []): array
    {
        $lastResult = ['ok' => false, 'vectors' => null, 'error' => 'No embedding providers configured in failover chain'];

        foreach ($this->providers as $provider) {
            if (!$provider instanceof EmbeddingProviderContract) {
                continue;
            }

            $result = $provider->embed($input, $options);

            if ($result['ok'] ?? false) {
                return $result;
            }

            $status = $result['status'] ?? null;
            $shouldFailover = !isset($status) || in_array((int) $status, $this->failoverStatuses, true);

            Log::warning('[TitanCore][ProviderFailoverChain] Embed provider failed', [
                'provider'   => $provider->providerName(),
                'status'     => $status,
                'error'      => $result['error'] ?? null,
                'failover'   => $shouldFailover,
            ]);

            $lastResult = $result;

            if (!$shouldFailover) {
                break;
            }
        }

        return $lastResult;
    }

    /**
     * Returns combined health — ok only if the primary provider is healthy.
     *
     * {@inheritDoc}
     */
    public function health(): array
    {
        $primary = $this->primaryProvider();
        if ($primary === null) {
            return ['ok' => false, 'provider' => 'failover_chain', 'reason' => 'No providers configured'];
        }

        $health = $primary->health();
        $health['provider'] = 'failover_chain(' . $primary->providerName() . ')';
        return $health;
    }

    /**
     * {@inheritDoc}
     */
    public function providerName(): string
    {
        $primary = $this->primaryProvider();
        return 'failover_chain(' . ($primary ? $primary->providerName() : 'empty') . ')';
    }

    /**
     * Replace the failover status list at runtime.
     */
    public function setFailoverStatuses(array $statuses): static
    {
        $this->failoverStatuses = $statuses;
        return $this;
    }

    /**
     * Return the first provider in the chain, or null if empty.
     */
    protected function primaryProvider(): ChatProviderContract|EmbeddingProviderContract|null
    {
        foreach ($this->providers as $p) {
            if ($p instanceof ChatProviderContract || $p instanceof EmbeddingProviderContract) {
                return $p;
            }
        }
        return null;
    }
}
