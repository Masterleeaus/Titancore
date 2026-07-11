<?php

namespace TitanSDK\Contracts\AI;

interface EmbeddingProviderContract
{
    /**
     * Generate embedding vector(s) for the given input.
     *
     * @param  string|array  $input   Single string or array of strings for batch embedding.
     * @param  array         $options Optional overrides: model, dimensions, timeout, ...
     * @return array{
     *   ok: bool,
     *   vectors: list<list<float>>|null,
     *   usage: array{prompt_tokens: int, total_tokens: int}|null,
     *   model: string|null,
     *   latency_ms: int,
     *   provider: string,
     *   error: string|null,
     * }
     */
    public function embed(string|array $input, array $options = []): array;

    /**
     * Check provider reachability and configuration.
     *
     * @return array{ok: bool, provider: string, reason: string|null}
     */
    public function health(): array;

    /**
     * Return the canonical provider identifier (e.g. "openai", "local").
     */
    public function providerName(): string;
}
