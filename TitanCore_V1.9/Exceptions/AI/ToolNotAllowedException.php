<?php

namespace Modules\TitanCore\Exceptions\AI;

use RuntimeException;

/**
 * Thrown by the tool executor when the requested tool is not present in the
 * configured allowlist.  Callers that surface this to HTTP should return 403.
 */
class ToolNotAllowedException extends RuntimeException
{
    public function __construct(string $toolName)
    {
        parent::__construct(
            "Tool \"{$toolName}\" is not in the allowed-tools list and cannot be executed."
        );
    }

    /** HTTP status code hint for exception renderers. */
    public function getStatusCode(): int
    {
        return 403;
    }
}
