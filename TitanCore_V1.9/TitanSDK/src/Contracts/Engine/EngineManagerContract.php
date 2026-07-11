<?php

namespace TitanSDK\Contracts\Engine;

interface EngineManagerContract
{
    public function discover(string $moduleDir): array;

    public function all(): array;

    public function validate(string $engineId): array;

    public function install(string $engineId): ?array;

    public function load(string $engineId): ?array;

    public function lifecycle(string $engineId, string $state): ?array;
}
