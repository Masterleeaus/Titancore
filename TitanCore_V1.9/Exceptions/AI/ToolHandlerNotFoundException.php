<?php

namespace Modules\TitanCore\Exceptions\AI;

use RuntimeException;

/**
 * Thrown by the tool executor when the handler class declared in the tool
 * manifest cannot be found (does not exist or is not auto-loadable).
 */
class ToolHandlerNotFoundException extends RuntimeException
{
    public function __construct(string $toolName, string $handlerClass)
    {
        parent::__construct(
            "Tool handler not found for tool \"{$toolName}\": class \"{$handlerClass}\" does not exist."
        );
    }
}
