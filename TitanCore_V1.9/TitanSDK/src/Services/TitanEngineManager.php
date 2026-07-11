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

    public function enable(string $engineId): ?array
    {
        return $this->manager->enable($engineId);
    }

    public function disable(string $engineId): ?array
    {
        return $this->manager->disable($engineId);
    }

    public function upgrade(string $engineId): ?array
    {
        return $this->manager->upgrade($engineId);
    }

    public function rollback(string $engineId): ?array
    {
        return $this->manager->rollback($engineId);
    }

    public function repair(string $engineId): ?array
    {
        return $this->manager->repair($engineId);
    }

    public function remove(string $engineId): ?array
    {
        return $this->manager->remove($engineId);
    }
}
