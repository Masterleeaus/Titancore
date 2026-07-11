<?php

namespace TitanSDK\Services;

use Modules\TitanCore\Services\TitanCoreAIService;
use Modules\TitanCore\Services\TitanCoreModelGateway;

/**
 * SDK-facing AI service that delegates all execution to TitanCore runtime services.
 *
 * External modules consume this through the TitanAI facade; no provider or runtime
 * logic is implemented here.
 */
final class TitanAIManager
{
    public function __construct(
        private readonly TitanCoreAIService $service,
        private readonly TitanCoreModelGateway $gateway,
    ) {}

    public function generate(
        string $prompt,
        array $messages = [],
        array $tools = [],
        ?string $provider = null,
        ?string $model = null,
    ): string {
        return $this->service->generate($prompt, $messages, $tools, $provider, $model);
    }

    public function embed(string $text, string $model): array
    {
        return $this->service->embed($text, $model);
    }

    public function embedBatch(array $texts, string $model): array
    {
        return $this->service->embedBatch($texts, $model);
    }

    public function chat(array $messages, array $context = [], array $options = []): array
    {
        return $this->gateway->chat($messages, $context, $options);
    }

    public function health(?string $provider = null): array
    {
        return $this->gateway->health($provider);
    }
}
