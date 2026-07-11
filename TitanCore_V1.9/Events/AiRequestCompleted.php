<?php

namespace Modules\TitanCore\Events;

/**
 * AiRequestCompleted
 *
 * Fired after every AI request (chat or embedding) completes, regardless of
 * success or failure.  Listeners use this event for cost tracking, alerting,
 * audit logging, and telemetry.
 *
 * This event is immutable: all properties are read-only.
 * The event schema is versioned via {@see $eventVersion}.
 */
class AiRequestCompleted
{
    /** Unique identifier correlating this event to its originating request. */
    public readonly string $correlationId;

    /** Outcome of the request: 'ok', 'error', 'timeout', 'rate_limited', etc. */
    public readonly string $status;

    /** Total wall-clock time of the request in milliseconds. */
    public readonly int $latencyMs;

    /** Number of prompt / input tokens consumed. */
    public readonly int $tokensIn;

    /** Number of completion / output tokens generated. */
    public readonly int $tokensOut;

    /** Estimated USD cost of the request. */
    public readonly float $cost;

    /** ISO 8601 UTC timestamp at which the request completed. */
    public readonly string $occurredAt;

    /** Semantic version of this event's payload schema. */
    public readonly string $eventVersion;

    /**
     * @param  string       $correlationId  Unique request correlation ID.
     * @param  string       $status         Outcome string (e.g. 'ok', 'error').
     * @param  int          $latencyMs      Request duration in milliseconds.
     * @param  int          $tokensIn       Prompt / input token count.
     * @param  int          $tokensOut      Completion / output token count.
     * @param  float        $cost           Estimated USD cost.
     * @param  string|null  $occurredAt     ISO 8601 timestamp; defaults to now().
     * @param  string       $eventVersion   Payload schema version.
     */
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
        $this->status        = $status;
        $this->latencyMs     = $latencyMs;
        $this->tokensIn      = $tokensIn;
        $this->tokensOut     = $tokensOut;
        $this->cost          = $cost;
        $this->occurredAt    = $occurredAt ?? gmdate('c');
        $this->eventVersion  = $eventVersion;
    }
}

