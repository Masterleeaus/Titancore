<?php

namespace Modules\TitanCore\Exceptions\AI;

use RuntimeException;

/**
 * Thrown by the tool executor when the active user lacks the required
 * permission to execute the requested tool.  HTTP callers should return 403.
 */
class ToolPermissionDeniedException extends RuntimeException
{
    public function __construct(string $toolName)
    {
        parent::__construct(
            "Permission denied: the current user is not authorised to execute tool \"{$toolName}\"."
        );
    }

    /** HTTP status code hint for exception renderers. */
    public function getStatusCode(): int
    {
        return 403;
    }
}
