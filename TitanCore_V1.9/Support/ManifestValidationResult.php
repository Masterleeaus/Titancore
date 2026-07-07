<?php

namespace Modules\TitanCore\Support;

/**
 * Result of validating a single manifest file against its JSON Schema.
 */
class ManifestValidationResult
{
    public const STATUS_SUCCESS = 'success';
    public const STATUS_WARNING = 'warning';
    public const STATUS_FAILURE = 'failure';

    /**
     * @param  string    $label    Manifest file name or identifier.
     * @param  string    $status   One of STATUS_* constants.
     * @param  string[]  $errors   Validation error messages.
     * @param  string[]  $warnings Validation warning messages.
     */
    private function __construct(
        private readonly string $label,
        private readonly string $status,
        private readonly array $errors,
        private readonly array $warnings,
    ) {}

    public static function success(string $label): self
    {
        return new self($label, self::STATUS_SUCCESS, [], []);
    }

    /** @param string[] $warnings */
    public static function warning(string $label, array $warnings): self
    {
        return new self($label, self::STATUS_WARNING, [], $warnings);
    }

    /** @param string[] $errors */
    public static function failure(string $label, array $errors): self
    {
        return new self($label, self::STATUS_FAILURE, $errors, []);
    }

    public function label(): string
    {
        return $this->label;
    }

    public function isValid(): bool
    {
        return $this->status !== self::STATUS_FAILURE;
    }

    public function hasWarnings(): bool
    {
        return $this->status === self::STATUS_WARNING;
    }

    public function status(): string
    {
        return $this->status;
    }

    /** @return string[] */
    public function errors(): array
    {
        return $this->errors;
    }

    /** @return string[] */
    public function warnings(): array
    {
        return $this->warnings;
    }

    /** @return string[] All messages (errors + warnings). */
    public function allMessages(): array
    {
        return array_merge($this->errors, $this->warnings);
    }
}
