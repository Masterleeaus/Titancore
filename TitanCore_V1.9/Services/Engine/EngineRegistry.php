<?php

namespace Modules\TitanCore\Services\Engine;

class EngineRegistry
{
    /** @var array<string,array> */
    private array $engines = [];

    public function sync(array $engines): array
    {
        $this->engines = [];

        foreach ($engines as $engine) {
            if (! is_array($engine) || ! isset($engine['id']) || ! is_string($engine['id'])) {
                continue;
            }

            $this->engines[$engine['id']] = $engine;
        }

        return $this->all();
    }

    public function put(array $engine): void
    {
        if (! isset($engine['id']) || ! is_string($engine['id'])) {
            return;
        }

        $this->engines[$engine['id']] = $engine;
    }

    public function all(): array
    {
        return array_values($this->engines);
    }

    public function find(string $id): ?array
    {
        return $this->engines[$id] ?? null;
    }
}
