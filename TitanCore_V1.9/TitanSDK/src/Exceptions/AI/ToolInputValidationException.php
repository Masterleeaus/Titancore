<?php

namespace TitanSDK\Exceptions\AI;

use InvalidArgumentException;

/**
 * Thrown by the tool executor when the provided parameters fail validation
 * against the tool's declared input schema.
 */
class ToolInputValidationException extends InvalidArgumentException
{
    /** @param  array<string,string>  $errors  Field-level validation messages. */
    public function __construct(string $toolName, public readonly array $errors)
    {
        $summary = implode('; ', array_map(
            fn($field, $msg) => "{$field}: {$msg}",
            array_keys($errors),
            array_values($errors),
        ));

        parent::__construct(
            "Input validation failed for tool \"{$toolName}\": {$summary}"
        );
    }
}
