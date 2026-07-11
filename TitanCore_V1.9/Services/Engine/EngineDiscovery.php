<?php

namespace Modules\TitanCore\Services\Engine;

use Modules\TitanCore\Services\AssetDiscoveryService;

class EngineDiscovery
{
    public function __construct(
        private readonly AssetDiscoveryService $assets,
    ) {}

    public function discover(string $moduleDir): array
    {
        $discovered = $this->assets->discoverAll($moduleDir);

        return $discovered['engines'] ?? [];
    }
}
