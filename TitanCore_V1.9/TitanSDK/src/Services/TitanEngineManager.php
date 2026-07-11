<?php

namespace TitanSDK\Services;

use TitanSDK\Contracts\Engine\EngineManagerContract;
use Modules\TitanCore\Services\Engine\EngineManager;

class TitanEngineManager implements EngineManagerContract
{
    public function __construct(
        private readonly EngineManager $manager,
    ) {}

    public function discover(string $moduleDir): array
    {
        return $this->manager->discover($moduleDir);
    }

    public function all(): array
    {
        return $this->manager->all();
    }

    public function validate(string $engineId): array
    {
        return $this->manager->validate($engineId);
    }

    public function install(string $engineId): ?array
    {
        return $this->manager->install($engineId);
    }

    public function load(string $engineId): ?array
    {
        return $this->manager->load($engineId);
    }

    public function lifecycle(string $engineId, string $state): ?array
    {
        return $this->manager->lifecycle($engineId, $state);
    }
}
