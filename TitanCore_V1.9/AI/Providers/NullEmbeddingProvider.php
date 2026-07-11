<?php

namespace Modules\TitanCore\AI\Providers;

use Modules\TitanCore\Contracts\AI\EmbeddingProviderContract;

/**
 * NullEmbeddingProvider
 *
 * A safe, no-op embedding provider that returns a deterministic zero vector of
 * fixed length without making any network calls.  Used as the default when no
 * real embedding provider is configured, ensuring that code paths that rely on
 * embeddings remain functional (returning neutral zero-vectors) in environments
 * where an API key has not been set.
 */
class NullEmbeddingProvider implements EmbeddingProviderContract
{
    protected string $provider = 'null';

    public function __construct(
        protected int $dimensions = 1536,
    ) {}

    public function embed(string|array $input, array $options = []): array
    {
        $inputs = is_array($input) ? $input : [$input];
        $zero   = array_fill(0, $this->dimensions, 0.0);

        return [
            'ok'         => true,
            'vectors'    => array_fill(0, count($inputs), $zero),
            'usage'      => null,
            'model'      => 'null',
            'latency_ms' => 0,
            'provider'   => $this->provider,
            'error'      => null,
        ];
    }

    public function health(): array
    {
        return [
            'ok'       => true,
            'provider' => $this->provider,
            'reason'   => null,
        ];
    }

    public function providerName(): string
    {
        return $this->provider;
    }
}
