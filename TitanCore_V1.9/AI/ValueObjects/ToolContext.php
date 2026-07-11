<?php

namespace Modules\TitanCore\AI\ValueObjects;

/**
 * Immutable execution context shared across tool runtime boundaries.
 */
final class ToolContext
{
    /**
     * @param  array<int, string>  $callStack
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public readonly mixed $user = null,
        public readonly mixed $userId = null,
        public readonly mixed $companyId = null,
        public readonly bool $dryRun = false,
        public readonly ?string $correlationId = null,
        public readonly array $callStack = [],
        public readonly array $meta = [],
    ) {}

    /**
     * Build a context object from the legacy array shape.
     *
     * @param  array<string, mixed>  $context
     */
    public static function fromArray(array $context = []): self
    {
        return new self(
            user: $context['user'] ?? null,
            userId: $context['user_id'] ?? null,
            companyId: $context['company_id'] ?? null,
            dryRun: (bool) ($context['dry_run'] ?? false),
            correlationId: isset($context['correlation_id']) && is_string($context['correlation_id']) && $context['correlation_id'] !== ''
                ? $context['correlation_id']
                : null,
            callStack: array_values(array_filter(
                array_map('strval', (array) ($context['call_stack'] ?? [])),
                static fn (string $tool) => $tool !== ''
            )),
            meta: is_array($context['meta'] ?? null) ? $context['meta'] : [],
        );
    }

    /**
     * Push a tool name onto the execution stack.
     */
    public function withTool(string $toolName): self
    {
        $stack = $this->callStack;
        $stack[] = $toolName;

        return new self(
            user: $this->user,
            userId: $this->userId,
            companyId: $this->companyId,
            dryRun: $this->dryRun,
            correlationId: $this->correlationId,
            callStack: $stack,
            meta: $this->meta,
        );
    }

    public function depth(): int
    {
        return count($this->callStack);
    }

    public function hasTool(string $toolName): bool
    {
        return in_array($toolName, $this->callStack, true);
    }

    /**
     * Export the legacy array shape.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'user'          => $this->user,
            'user_id'       => $this->userId,
            'company_id'    => $this->companyId,
            'dry_run'       => $this->dryRun,
            'correlation_id'=> $this->correlationId,
            'call_stack'    => $this->callStack,
            'meta'          => $this->meta,
        ];
    }
}
