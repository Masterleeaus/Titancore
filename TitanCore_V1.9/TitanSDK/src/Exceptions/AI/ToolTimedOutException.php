<?php

namespace TitanSDK\Exceptions\AI;

use RuntimeException;

/**
 * Thrown by the tool executor when a tool handler exceeds the configured
 * execution timeout.  The execution is logged with status "timed_out".
 */
class ToolTimedOutException extends RuntimeException
{
    public function __construct(string $toolName, int $timeoutSeconds)
    {
        parent::__construct(
            "Tool \"{$toolName}\" exceeded the maximum execution time of {$timeoutSeconds}s and was terminated."
        );
    }
}
