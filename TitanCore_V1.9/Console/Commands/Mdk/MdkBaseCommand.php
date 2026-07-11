<?php

namespace Modules\TitanCore\Console\Commands\Mdk;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Abstract base for all Titan MDK generator commands.
 *
 * Provides shared utilities for:
 *  - Stub rendering with variable substitution
 *  - Idempotent file writing with dry-run and overwrite-protection support
 *  - Name normalisation helpers (PascalCase, snake_case, kebab-case)
 *  - Coloured console progress output
 */
abstract class MdkBaseCommand extends Command
{
    /** @var list<string> Paths written during this invocation (used in summary). */
    protected array $written = [];

    /** @var list<string> Paths skipped (already exist, --force not given). */
    protected array $skipped = [];

    // ──────────────────────────────────────────────────────────────────────────
    // Name helpers
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Convert an arbitrary name to PascalCase.
     *
     * Examples: "quote_generator" → "QuoteGenerator", "CRM" → "CRM"
     */
    protected function toPascal(string $name): string
    {
        return Str::studly($name);
    }

    /**
     * Convert to snake_case.
     */
    protected function toSnake(string $name): string
    {
        return Str::snake($name);
    }

    /**
     * Convert to kebab-case.
     */
    protected function toKebab(string $name): string
    {
        return Str::kebab($name);
    }

    /**
     * Build the standard variable map used by most stubs.
     *
     * @return array<string, string>
     */
    protected function buildVars(string $name, ?string $module = null): array
    {
        $pascal  = $this->toPascal($name);
        $snake   = $this->toSnake($name);
        $kebab   = $this->toKebab($name);
        $modName = $module ? $this->toPascal($module) : $pascal;

        return [
            '{{ Name }}'       => $pascal,
            '{{ name }}'       => $snake,
            '{{ slug }}'       => $kebab,
            '{{ ModuleName }}' => $modName,
            '{{ Namespace }}'  => "Modules\\{$modName}",
            '{{ Year }}'       => (string) date('Y'),
        ];
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Stub rendering
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Path to the stubs directory.
     */
    protected function stubsPath(): string
    {
        return dirname(__DIR__, 2).'/Stubs';
    }

    /**
     * Load and render a stub file, substituting all provided variables.
     *
     * @param  array<string, string>  $vars
     */
    protected function renderStub(string $stub, array $vars): string
    {
        $path = $this->stubsPath().'/'.$stub;

        if (! file_exists($path)) {
            throw new \RuntimeException("Stub not found: {$path}");
        }

        $content = file_get_contents($path);

        return str_replace(array_keys($vars), array_values($vars), $content);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // File writing
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Write $content to $absolutePath, respecting --dry-run and --force.
     *
     * Returns true if the file was written (or would be in dry-run).
     */
    protected function writeFile(string $absolutePath, string $content): bool
    {
        $isDryRun = (bool) $this->option('dry-run');
        $isForce  = (bool) $this->option('force');
        $rel      = $this->relativePath($absolutePath);

        if (file_exists($absolutePath) && ! $isForce) {
            $this->skipped[] = $absolutePath;
            $this->components->twoColumnDetail(
                "<fg=yellow>SKIP</>  {$rel}",
                '<fg=gray>exists (use --force to overwrite)</>'
            );

            return false;
        }

        if ($isDryRun) {
            $this->written[] = $absolutePath;
            $this->components->twoColumnDetail(
                "<fg=cyan>DRY</>   {$rel}",
                '<fg=gray>would write</>'
            );

            return true;
        }

        $this->ensureDir(dirname($absolutePath));
        file_put_contents($absolutePath, $content);
        $this->written[] = $absolutePath;
        $this->components->twoColumnDetail(
            "<fg=green>CREATE</> {$rel}",
            ''
        );

        return true;
    }

    /**
     * Ensure a directory exists (no-op in dry-run mode).
     */
    protected function ensureDir(string $dir): void
    {
        if (! $this->option('dry-run') && ! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }

    /**
     * Write a placeholder .gitkeep file to keep an empty directory tracked.
     */
    protected function writeGitkeep(string $dir): void
    {
        $this->writeFile($dir.'/.gitkeep', '');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Summary output
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Print a creation summary after all files have been written.
     */
    protected function printSummary(): void
    {
        $this->newLine();

        $count   = count($this->written);
        $skipped = count($this->skipped);

        if ($count > 0) {
            $this->components->info(
                sprintf(
                    '%d file(s) %s%s.',
                    $count,
                    $this->option('dry-run') ? 'would be written' : 'written',
                    $skipped > 0 ? ", {$skipped} skipped" : ''
                )
            );
        } else {
            $this->components->warn('No files written.');
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Path utilities
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Base path for modules.
     *
     * When the configured value is already an absolute path (begins with the
     * directory separator), it is used as-is. Otherwise it is resolved
     * relative to the application base path. This allows tests to inject
     * a temporary directory by setting an absolute path in config.
     */
    protected function modulesPath(string ...$segments): string
    {
        $configured = (string) config('titan-modules.path', 'Modules');
        $base       = str_starts_with($configured, DIRECTORY_SEPARATOR)
            ? $configured
            : base_path($configured);

        return $segments ? $base.'/'.implode('/', $segments) : $base;
    }

    /**
     * Strip base_path prefix for display purposes.
     */
    protected function relativePath(string $absolute): string
    {
        $base = base_path();

        return str_starts_with($absolute, $base)
            ? ltrim(substr($absolute, strlen($base)), '/')
            : $absolute;
    }
}
