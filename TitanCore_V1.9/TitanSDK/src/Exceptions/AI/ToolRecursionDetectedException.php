<?php

namespace TitanSDK\Exceptions\AI;

use RuntimeException;

/**
 * Thrown when a tool re-enters the executor too deeply or recursively.
 */
class ToolRecursionDetectedException extends RuntimeException
{
    public function __construct(string $toolName, int $depth)
    {
        parent::__construct(
            "Recursive tool execution detected for \"{$toolName}\" at depth {$depth}."
        );
    }
}
