<?php

namespace Modules\TitanCore\Services\Upgrade;

use Illuminate\Support\Str;

/**
 * Checks that the current runtime satisfies the PHP and Laravel version
 * constraints declared in a module manifest.
 *
 * module.json schema additions:
 *   "requires_php":     "^8.2"      (semver / composer constraint)
 *   "requires_laravel": "^11.0"
 */
class VersionCompatibilityChecker
{
    /**
     * @param  array<string, mixed>  $manifest  Decoded module.json
     * @return array{ok: bool, errors: string[]}
     */
    public function check(array $manifest): array
    {
        $errors = [];

        if (isset($manifest['requires_php'])) {
            if (! $this->satisfies(PHP_VERSION, $manifest['requires_php'])) {
                $errors[] = sprintf(
                    'PHP %s does not satisfy required constraint "%s".',
                    PHP_VERSION,
                    $manifest['requires_php']
                );
            }
        }

        if (isset($manifest['requires_laravel'])) {
            $laravelVersion = $this->laravelVersion();
            if (! $this->satisfies($laravelVersion, $manifest['requires_laravel'])) {
                $errors[] = sprintf(
                    'Laravel %s does not satisfy required constraint "%s".',
                    $laravelVersion,
                    $manifest['requires_laravel']
                );
            }
        }

        return ['ok' => empty($errors), 'errors' => $errors];
    }

    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Evaluate a semver-style constraint (e.g. "^8.2", ">=8.1", "~11.0").
     * Supports: ^, ~, >=, <=, >, <, = and bare version strings.
     */
    public function satisfies(string $actual, string $constraint): bool
    {
        $constraint = trim($constraint);

        // Wildcard / always-pass
        if ($constraint === '*' || $constraint === '') {
            return true;
        }

        // Strip build/pre-release metadata for comparison
        $actual = $this->normalise($actual);

        // Handle AND constraints joined by space or comma
        $parts = preg_split('/\s*,\s*|\s+/', $constraint);
        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }
            if (! $this->evaluateSingleConstraint($actual, $part)) {
                return false;
            }
        }

        return true;
    }

    private function evaluateSingleConstraint(string $actual, string $constraint): bool
    {
        // Caret: ^1.2 → >=1.2.0, <2.0.0
        if (str_starts_with($constraint, '^')) {
            $ver = ltrim($constraint, '^');
            return $this->caretSatisfied($actual, $ver);
        }

        // Tilde: ~1.2 → >=1.2.0, <1.3.0
        if (str_starts_with($constraint, '~')) {
            $ver = ltrim($constraint, '~');
            return $this->tildeSatisfied($actual, $ver);
        }

        if (str_starts_with($constraint, '>=')) {
            return version_compare($actual, $this->normalise(ltrim($constraint, '>=')), '>=');
        }
        if (str_starts_with($constraint, '<=')) {
            return version_compare($actual, $this->normalise(ltrim($constraint, '<=')), '<=');
        }
        if (str_starts_with($constraint, '>')) {
            return version_compare($actual, $this->normalise(ltrim($constraint, '>')), '>');
        }
        if (str_starts_with($constraint, '<')) {
            return version_compare($actual, $this->normalise(ltrim($constraint, '<')), '<');
        }
        if (str_starts_with($constraint, '=')) {
            return version_compare($actual, $this->normalise(ltrim($constraint, '=')), '=');
        }

        // Bare version: exact match
        return version_compare($actual, $this->normalise($constraint), '=');
    }

    private function caretSatisfied(string $actual, string $min): bool
    {
        $min = $this->normalise($min);
        $parts = explode('.', $min);

        if ((int) ($parts[0] ?? 0) > 0) {
            $upper = ((int) $parts[0] + 1) . '.0.0';
        } elseif ((int) ($parts[1] ?? 0) > 0) {
            $upper = '0.' . ((int) $parts[1] + 1) . '.0';
        } else {
            $upper = '0.0.' . ((int) ($parts[2] ?? 0) + 1);
        }

        return version_compare($actual, $min, '>=') && version_compare($actual, $upper, '<');
    }

    private function tildeSatisfied(string $actual, string $min): bool
    {
        $min = $this->normalise($min);
        $parts = explode('.', $min);
        $upper = ($parts[0] ?? '0') . '.' . ((int) ($parts[1] ?? 0) + 1) . '.0';

        return version_compare($actual, $min, '>=') && version_compare($actual, $upper, '<');
    }

    /** Strip pre-release/-metadata, ensure at least X.Y.Z */
    private function normalise(string $version): string
    {
        // Strip stability flags (e.g. "11.0-dev", "8.2.0RC1")
        // preg_replace returns string|null; cast to string to satisfy explode().
        $version = (string) preg_replace('/[-+][a-zA-Z0-9.]+$/', '', trim($version));
        $parts   = explode('.', $version);

        while (count($parts) < 3) {
            $parts[] = '0';
        }

        return implode('.', array_slice($parts, 0, 3));
    }

    private function laravelVersion(): string
    {
        if (function_exists('app')) {
            try {
                return app()->version();
            } catch (\Throwable) {
                // Fall through
            }
        }

        if (class_exists(\Illuminate\Foundation\Application::class)) {
            try {
                return \Illuminate\Foundation\Application::VERSION;
            } catch (\Throwable) {
                // Fall through
            }
        }

        return '0.0.0';
    }
}
