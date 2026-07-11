<?php

namespace Modules\TitanCore\AI\ValueObjects;

class ToolContext extends \TitanSDK\ValueObjects\ToolContext
{
    /**
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
}
