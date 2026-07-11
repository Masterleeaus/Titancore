<?php

namespace TitanSDK\ValueObjects;

/**
 * Immutable result envelope returned by every tool execution.
 *
 * Not final so the legacy Modules\TitanCore compatibility wrapper can extend it
 * during the TitanSDK extraction transition. External SDK consumers should treat
 * this as effectively final and should not subclass it.
 */
class ToolResult
{
    public function __construct(
        /** Whether the tool call succeeded. */
        public readonly bool $ok,

        /** The tool name that was invoked. */
        public readonly string $tool,

        /** Normalised output data from the handler. */
        public readonly array $data,

        /** Human-readable status or error message. */
        public readonly string $message,

        /** Optional warnings produced during execution. */
        public readonly array $warnings = [],

        /** Optional reference token for auditing / idempotency. */
        public readonly ?string $auditRef = null,
    ) {}

    /** Serialise to a plain array for API responses. */
    public function toArray(): array
    {
        return [
            'ok'        => $this->ok,
            'tool'      => $this->tool,
            'data'      => $this->data,
            'message'   => $this->message,
            'warnings'  => $this->warnings,
            'audit_ref' => $this->auditRef,
        ];
    }
}
