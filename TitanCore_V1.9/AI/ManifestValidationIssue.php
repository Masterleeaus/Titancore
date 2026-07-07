<?php

namespace Modules\TitanCore\AI;

/**
 * Represents a single issue found during ai_tools.json manifest validation.
 */
class ManifestValidationIssue
{
    public const LEVEL_ERROR   = 'error';
    public const LEVEL_WARNING = 'warning';

    private string $module;
    private string $tool;
    private string $level;
    private string $message;

    private function __construct(string $module, string $tool, string $level, string $message)
    {
        $this->module  = $module;
        $this->tool    = $tool;
        $this->level   = $level;
        $this->message = $message;
    }

    public static function error(string $module, string $tool, string $message): self
    {
        return new self($module, $tool, self::LEVEL_ERROR, $message);
    }

    public static function warning(string $module, string $tool, string $message): self
    {
        return new self($module, $tool, self::LEVEL_WARNING, $message);
    }

    public function module(): string
    {
        return $this->module;
    }

    public function tool(): string
    {
        return $this->tool;
    }

    public function level(): string
    {
        return $this->level;
    }

    public function message(): string
    {
        return $this->message;
    }

    public function isError(): bool
    {
        return $this->level === self::LEVEL_ERROR;
    }

    public function isWarning(): bool
    {
        return $this->level === self::LEVEL_WARNING;
    }
}
