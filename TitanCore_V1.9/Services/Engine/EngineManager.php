<?php

namespace Modules\TitanCore\Services\Engine;

use Modules\TitanCore\Contracts\Engine\EngineManagerContract;

class EngineManager implements EngineManagerContract
{
    public function __construct(
        private readonly EngineDiscovery $discovery,
        private readonly EngineRegistry $registry,
        private readonly EngineValidator $validator,
        private readonly EngineInstaller $installer,
        private readonly EngineLoader $loader,
        private readonly EngineLifecycle $lifecycle,
    ) {}

    public function discover(string $moduleDir): array
    {
        return $this->registry->sync($this->discovery->discover($moduleDir));
    }

    public function all(): array
    {
        return $this->registry->all();
    }

    public function validate(string $engineId): array
    {
        $engine = $this->registry->find($engineId);

        if ($engine === null) {
            return ['valid' => false, 'errors' => ['Engine not found.']];
        }

        return $this->validator->validateEngine($engine);
    }

    public function install(string $engineId): ?array
    {
        $engine = $this->registry->find($engineId);

        if ($engine === null) {
            return null;
        }

        $engine = $this->installer->install($engine);
        $this->registry->put($engine);

        return $engine;
    }

    public function load(string $engineId): ?array
    {
        $engine = $this->registry->find($engineId);

        if ($engine === null) {
            return null;
        }

        $engine = $this->loader->load($engine);
        $this->registry->put($engine);

        return $engine;
    }

    public function lifecycle(string $engineId, string $state): ?array
    {
        $engine = $this->registry->find($engineId);

        if ($engine === null) {
            return null;
        }

        $engine = $this->lifecycle->transition($engine, $state);
        $this->registry->put($engine);

        return $engine;
    }

    public function enable(string $engineId): ?array
    {
        return $this->lifecycle($engineId, 'active');
    }

    public function disable(string $engineId): ?array
    {
        return $this->lifecycle($engineId, 'disabled');
    }

    public function upgrade(string $engineId): ?array
    {
        $engine = $this->lifecycle($engineId, 'upgrading');

        if ($engine === null) {
            return null;
        }

        $engine['upgrade_requested_at'] = gmdate('c');
        $this->registry->put($engine);

        return $engine;
    }

    public function rollback(string $engineId): ?array
    {
        return $this->lifecycle($engineId, 'maintenance');
    }

    public function repair(string $engineId): ?array
    {
        return $this->lifecycle($engineId, 'ready');
    }

    public function remove(string $engineId): ?array
    {
        return $this->lifecycle($engineId, 'removed');
    }
}
