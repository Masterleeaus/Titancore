<?php

namespace TitanSDK\Events;

class EngineLifecycleChanged
{
    public readonly string $engineId;
    public readonly string $from;
    public readonly string $to;
    public readonly string $occurredAt;
    public readonly string $eventVersion;

    public function __construct(
        string $engineId,
        string $from,
        string $to,
        ?string $occurredAt = null,
        string $eventVersion = '1.0.0',
    ) {
        $this->engineId = $engineId;
        $this->from = $from;
        $this->to = $to;
        $this->occurredAt = $occurredAt ?? gmdate('c');
        $this->eventVersion = $eventVersion;
    }
}
