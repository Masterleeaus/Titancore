<?php

namespace TitanSDK\Events;

class EngineValidated
{
    public readonly string $engineId;
    public readonly bool $valid;
    public readonly array $errors;
    public readonly ?string $correlationId;
    public readonly string $occurredAt;
    public readonly string $eventVersion;

    public function __construct(
        string $engineId,
        bool $valid,
        array $errors = [],
        ?string $occurredAt = null,
        string $eventVersion = '1.0.0',
        ?string $correlationId = null,
    ) {
        $this->engineId = $engineId;
        $this->valid = $valid;
        $this->errors = $errors;
        $this->correlationId = $correlationId;
        $this->occurredAt = $occurredAt ?? gmdate('c');
        $this->eventVersion = $eventVersion;
    }
}
