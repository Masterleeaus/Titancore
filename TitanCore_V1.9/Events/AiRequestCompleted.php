<?php

namespace Modules\TitanCore\Events;

class AiRequestCompleted
{
  public readonly string $correlationId;
  public readonly string $status;
  public readonly int $latencyMs;
  public readonly int $tokensIn;
  public readonly int $tokensOut;
  public readonly float $cost;
  public readonly string $occurredAt;
  public readonly string $eventVersion;

  public function __construct(
    string $correlationId,
    string $status,
    int $latencyMs,
    int $tokensIn,
    int $tokensOut,
    float $cost,
    ?string $occurredAt = null,
    string $eventVersion = '1.0.0'
  ) {
    $this->correlationId = $correlationId;
    $this->status = $status;
    $this->latencyMs = $latencyMs;
    $this->tokensIn = $tokensIn;
    $this->tokensOut = $tokensOut;
    $this->cost = $cost;
    $this->occurredAt = $occurredAt ?? gmdate('c');
    $this->eventVersion = $eventVersion;
  }
}
