<?php

namespace TitanSDK\Events;

class EngineInstalled
{
    public readonly string $engineId;
    public readonly string $version;
    public readonly string $occurredAt;
    public readonly string $eventVersion;

    public function __construct(
        string $engineId,
        string $version,
        ?string $occurredAt = null,
        string $eventVersion = '1.0.0',
    ) {
        $this->engineId = $engineId;
        $this->version = $version;
        $this->occurredAt = $occurredAt ?? gmdate('c');
        $this->eventVersion = $eventVersion;
    }
}
