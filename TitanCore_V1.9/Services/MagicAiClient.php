<?php

namespace Modules\TitanCore\Services;

/**
 * Backwards-compatible alias for legacy MagicAI integrations.
 *
 * Extends TitanAiClient to preserve compatibility while reusing canonical behavior.
 */
class MagicAiClient extends TitanAiClient
{
    public function __construct(
        string $baseUrl,
        string $apiKey,
        int $timeoutSeconds = 60,
    ) {
        parent::__construct($baseUrl, $apiKey, $timeoutSeconds);
    }
}
