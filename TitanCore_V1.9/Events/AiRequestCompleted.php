<?php
namespace Modules\TitanCore\Events;
class AiRequestCompleted {
  public function __construct(
    public string $correlationId,
    public string $status,
    public int $latencyMs,
    public int $tokensIn,
    public int $tokensOut,
    public float $cost
  ) {}
}