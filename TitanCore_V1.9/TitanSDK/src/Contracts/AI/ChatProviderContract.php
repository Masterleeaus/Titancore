<?php

namespace TitanSDK\Contracts\AI;

interface ChatProviderContract
{
    /**
     * Send a chat completion request to the provider.
     *
     * @param  array  $messages  OpenAI-style messages: [['role'=>'user','content'=>'...']]
     * @param  array  $options   Optional overrides: model, temperature, max_tokens, timeout, ...
     * @return array{
     *   ok: bool,
     *   content: string|null,
     *   usage: array{prompt_tokens: int, completion_tokens: int, total_tokens: int}|null,
     *   model: string|null,
     *   latency_ms: int,
     *   provider: string,
     *   error: string|null,
     * }
     */
    public function chat(array $messages, array $options = []): array;

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
