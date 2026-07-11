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
    protected int $backoffBaseDelayMs = 0;
    protected int $backoffMaxDelayMs = 0;
    protected int $circuitBreakerFailureThreshold = 0;
    protected int $circuitBreakerCooldownSeconds = 60;
    /** @var callable(int):void|null */
    protected $sleeper = null;
    /** @var callable():float|null */
    protected $clock = null;
    protected object $stateStore;

    /**
     * @param  ChatProviderContract[]|EmbeddingProviderContract[]  $providers
     *         Ordered list of providers; first entry is primary.
     */
    public function __construct(protected array $providers = [])
    {
        $this->stateStore = self::newStateStore();
    }

    public static function newStateStore(): object
    {
        return (object) [
            'failureCounts' => [],
            'cooldownUntil' => [],
        ];
    }

    /**
     * Try each provider in order for chat completions.
     * Falls over to the next provider on error or failover-eligible HTTP status.
     *
     * {@inheritDoc}
     */
    public function chat(array $messages, array $options = []): array
    {
        $lastResult = ['ok' => false, 'content' => null, 'error' => 'No chat providers configured in failover chain'];

        foreach ($this->providers as $index => $provider) {
            if (!$provider instanceof ChatProviderContract) {
                continue;
            }

            $providerName = $provider->providerName();
            if ($this->isProviderCircuitOpen($providerName)) {
                $lastResult = $this->buildCircuitOpenChatResult($providerName);

                Log::warning('[TitanCore][ProviderFailoverChain] Chat provider temporarily skipped', [
                    'provider' => $providerName,
                    'reason'   => 'circuit_open',
                ]);

                continue;
            }

            $result = $provider->chat($messages, $options);

            if ($result['ok'] ?? false) {
                $this->resetProviderFailureState($providerName);
                return $result;
            }

            $status = $result['status'] ?? null;
            $shouldFailover = !isset($status) || in_array((int) $status, $this->failoverStatuses, true);

            Log::warning('[TitanCore][ProviderFailoverChain] Chat provider failed', [
                'provider'   => $providerName,
                'status'     => $status,
                'error'      => $result['error'] ?? null,
                'failover'   => $shouldFailover,
            ]);

            $lastResult = $result;

            if ($shouldFailover) {
                $failureCount = $this->recordProviderFailure($providerName);
                $this->applyBackoffDelay($failureCount, $this->hasNextProvider($index, ChatProviderContract::class));
                continue;
            }

            $this->resetProviderFailureState($providerName);
            // Non-retryable error (e.g. 400 bad request) — stop chain.
            break;
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

        foreach ($this->providers as $index => $provider) {
            if (!$provider instanceof EmbeddingProviderContract) {
                continue;
            }

            $providerName = $provider->providerName();
            if ($this->isProviderCircuitOpen($providerName)) {
                $lastResult = $this->buildCircuitOpenEmbeddingResult($providerName);

                Log::warning('[TitanCore][ProviderFailoverChain] Embed provider temporarily skipped', [
                    'provider' => $providerName,
                    'reason'   => 'circuit_open',
                ]);

                continue;
            }

            $result = $provider->embed($input, $options);

            if ($result['ok'] ?? false) {
                $this->resetProviderFailureState($providerName);
                return $result;
            }

            $status = $result['status'] ?? null;
            $shouldFailover = !isset($status) || in_array((int) $status, $this->failoverStatuses, true);

            Log::warning('[TitanCore][ProviderFailoverChain] Embed provider failed', [
                'provider'   => $providerName,
                'status'     => $status,
                'error'      => $result['error'] ?? null,
                'failover'   => $shouldFailover,
            ]);

            $lastResult = $result;

            if ($shouldFailover) {
                $failureCount = $this->recordProviderFailure($providerName);
                $this->applyBackoffDelay($failureCount, $this->hasNextProvider($index, EmbeddingProviderContract::class));
                continue;
            }

            $this->resetProviderFailureState($providerName);
            break;
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
     * Configure exponential backoff before trying the next provider.
     */
    public function setBackoff(int $baseDelayMs, int $maxDelayMs = 0): static
    {
        $this->backoffBaseDelayMs = max(0, $baseDelayMs);
        $this->backoffMaxDelayMs = max(0, $maxDelayMs);

        return $this;
    }

    /**
     * Configure an in-process circuit breaker for retryable provider failures.
     */
    public function setCircuitBreaker(int $failureThreshold, int $cooldownSeconds): static
    {
        $this->circuitBreakerFailureThreshold = max(0, $failureThreshold);
        $this->circuitBreakerCooldownSeconds = max(0, $cooldownSeconds);

        return $this;
    }

    /**
     * Override the sleep handler for deterministic tests.
     *
     * @param  callable(int):void|null  $sleeper
     */
    public function setSleeper(?callable $sleeper): static
    {
        $this->sleeper = $sleeper;

        return $this;
    }

    /**
     * Override the clock source for deterministic tests.
     *
     * @param  callable():float|null  $clock
     */
    public function setClock(?callable $clock): static
    {
        $this->clock = $clock;

        return $this;
    }

    /**
     * Reuse a shared state store across chain instances built by the same caller.
     */
    public function setStateStore(object $stateStore): static
    {
        $stateStore->failureCounts ??= [];
        $stateStore->cooldownUntil ??= [];
        $this->stateStore = $stateStore;

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

    protected function recordProviderFailure(string $providerName): int
    {
        $failures = (($this->stateStore->failureCounts[$providerName] ?? 0) + 1);
        $this->stateStore->failureCounts[$providerName] = $failures;

        if (
            $this->circuitBreakerFailureThreshold > 0
            && $this->circuitBreakerCooldownSeconds > 0
            && $failures >= $this->circuitBreakerFailureThreshold
        ) {
            $this->stateStore->cooldownUntil[$providerName] = $this->now() + $this->circuitBreakerCooldownSeconds;
        }

        return $failures;
    }

    protected function resetProviderFailureState(string $providerName): void
    {
        unset($this->stateStore->failureCounts[$providerName], $this->stateStore->cooldownUntil[$providerName]);
    }

    protected function isProviderCircuitOpen(string $providerName): bool
    {
        $cooldownUntil = $this->stateStore->cooldownUntil[$providerName] ?? null;

        if ($cooldownUntil === null) {
            return false;
        }

        if ($cooldownUntil <= $this->now()) {
            $this->resetProviderFailureState($providerName);
            return false;
        }

        return true;
    }

    protected function applyBackoffDelay(int $failureCount, bool $hasFallbackProvider): void
    {
        if ($this->backoffBaseDelayMs <= 0 || !$hasFallbackProvider) {
            return;
        }

        $exponent = min(max(0, $failureCount - 1), 10);
        $delayMs = $this->backoffBaseDelayMs * (2 ** $exponent);

        if ($this->backoffMaxDelayMs > 0) {
            $delayMs = min($delayMs, $this->backoffMaxDelayMs);
        }

        $sleep = $this->sleeper ?? static function (int $microseconds): void {
            usleep($microseconds);
        };
        $sleep((int) $delayMs * 1000);
    }

    /**
     * @param  class-string  $contract
     */
    protected function hasNextProvider(int $currentIndex, string $contract): bool
    {
        $providerCount = count($this->providers);

        for ($i = $currentIndex + 1; $i < $providerCount; $i++) {
            if ($this->providers[$i] instanceof $contract) {
                return true;
            }
        }

        return false;
    }

    protected function now(): float
    {
        $clock = $this->clock ?? static fn (): float => microtime(true);

        return (float) $clock();
    }

    protected function buildCircuitOpenChatResult(string $providerName): array
    {
        return [
            'ok'           => false,
            'content'      => null,
            'usage'        => null,
            'model'        => null,
            'latency_ms'   => 0,
            'provider'     => $providerName,
            'error'        => 'Provider temporarily unavailable due to circuit breaker',
            'status'       => 503,
            'circuit_open' => true,
        ];
    }

    protected function buildCircuitOpenEmbeddingResult(string $providerName): array
    {
        return [
            'ok'           => false,
            'vectors'      => null,
            'usage'        => null,
            'model'        => null,
            'latency_ms'   => 0,
            'provider'     => $providerName,
            'error'        => 'Provider temporarily unavailable due to circuit breaker',
            'status'       => 503,
            'circuit_open' => true,
        ];
    }
}
