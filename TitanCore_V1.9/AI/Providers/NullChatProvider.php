<?php

namespace Modules\TitanCore\AI\Providers;

use Modules\TitanCore\Contracts\AI\ChatProviderContract;

/**
 * NullChatProvider
 *
 * A safe, no-op chat provider that returns a predictable empty response without
 * making any network calls.  Used as the default when no real provider is
 * configured, preventing runtime errors in environments that have not yet set
 * up an AI provider key.
 */
class NullChatProvider implements ChatProviderContract
{
    protected string $provider = 'null';

    public function chat(array $messages, array $options = []): array
    {
        return [
            'ok'         => true,
            'content'    => '',
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
