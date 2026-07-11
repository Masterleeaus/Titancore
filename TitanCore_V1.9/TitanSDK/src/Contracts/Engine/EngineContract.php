<?php

namespace TitanSDK\Contracts\Engine;

interface EngineContract
{
    public function id(): string;

    public function name(): string;

    public function version(): string;

    public function lifecycle(): string;

    public function toArray(): array;
}
