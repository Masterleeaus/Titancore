<?php

namespace Modules\TitanCore\Contracts\AI;

use Modules\TitanCore\AI\ValueObjects\ToolResult;

interface ToolExecutorContract
{
    /**
     * Execute a declared AI tool by name with the provided parameters.
     *
     * Implementations must:
     * - Resolve the handler class declared in the tool manifest.
     * - Validate $params against the tool's declared input schema.
     * - Call the resolved handler.
     * - Return a consistent {@see ToolResult} value object.
     *
     * @param  string  $toolName  The tool identifier as declared in the AI manifest.
     * @param  array   $params    Input parameters to pass to the handler.
     * @param  array   $context   Optional runtime context (company_id, user, channel, …).
     *
     * @throws \Modules\TitanCore\Exceptions\AI\ToolHandlerNotFoundException   when the declared handler class does not exist.
     * @throws \Modules\TitanCore\Exceptions\AI\ToolInputValidationException  when $params fails the tool's declared input schema.
     *
     * @return ToolResult
     */
    public function execute(string $toolName, array $params, array $context = []): ToolResult;
}
