<?php

namespace Modules\TitanCore\Console\Commands\Mdk;

use Illuminate\Console\Command;

/**
 * Validate a specific Titan module against platform architecture rules.
 *
 * Usage:
 *   php artisan titan:validate-module CRM
 *   php artisan titan:validate-module CRM --strict
 *
 * This command is lighter than titan:doctor — it focuses on manifest and
 * namespace validation rather than deep class inspection.
 *
 * Exit codes:
 *   0 — all validations passed
 *   1 — one or more validations failed
 */
class ValidateModuleCommand extends Command
{
    protected $signature = 'titan:validate-module
                            {module : Module name to validate (e.g. CRM)}
                            {--strict : Treat warnings as failures}';

    protected $description = 'Validate a Titan module against platform architecture rules.';

    /** Maximum number of PHP files to sample for namespace validation. */
    private const MAX_NS_SAMPLE = 100;

    public function handle(): int
    {
        $moduleName = (string) $this->argument('module');
        $strict     = (bool) $this->option('strict');

        $modulesBase = $this->resolveModulesBase();
        $moduleDir   = $modulesBase.'/'.$moduleName;

        $this->components->info("Validating module <fg=cyan>{$moduleName}</>");
        $this->newLine();

        if (! is_dir($moduleDir)) {
            $this->components->error("Module directory not found: {$moduleDir}");
            $available = $this->discoverModules($modulesBase);

            if ($available !== []) {
                $this->newLine();
                $this->line('Available modules:');
                $this->line('  '.implode(', ', $available));
            }

            return self::FAILURE;
        }

        $errors   = [];
        $warnings = [];

        $checks = [
            fn () => $this->validateModuleJson($moduleDir, $moduleName, $errors, $warnings),
            fn () => $this->validateManifestFiles($moduleDir, $errors, $warnings),
            fn () => $this->validateNamespace($moduleDir, $moduleName, $warnings),
            fn () => $this->validateDependencies($moduleDir, $warnings),
        ];

        $this->withProgressBar($checks, function (callable $check): void {
            $check();
        });
        $this->newLine(2);

        // ── Output results ─────────────────────────────────────────────────────
        foreach ($errors as $error) {
            $this->line("  <fg=red>✗</> {$error}");
        }

        foreach ($warnings as $warning) {
            $this->line("  <fg=yellow>⚠</> {$warning}");
        }

        $errorCount   = count($errors);
        $warningCount = count($warnings);

        $this->components->twoColumnDetail('Errors', (string) $errorCount);
        $this->components->twoColumnDetail('Warnings', (string) $warningCount);
        $this->components->twoColumnDetail('Strict mode', $strict ? 'enabled' : 'disabled');
        $this->newLine();

        if ($errorCount > 0) {
            $this->components->error(
                "Validation failed: {$errorCount} error(s), {$warningCount} warning(s)."
            );

            return self::FAILURE;
        }

        if ($strict && $warningCount > 0) {
            $this->components->warn(
                "Strict mode: {$warningCount} warning(s) treated as failure."
            );

            return self::FAILURE;
        }

        if ($warningCount > 0) {
            $this->components->warn(
                "<fg=green>✓</> {$moduleName} passed with {$warningCount} warning(s)."
            );
        } else {
            $this->components->info("<fg=green>✓</> {$moduleName} passed all validations.");
        }

        return self::SUCCESS;
    }

    // ──────────────────────────────────────────────────────────────────────────

    /**
     * @param  list<string>  $errors
     * @param  list<string>  $warnings
     */
    private function validateModuleJson(
        string $moduleDir,
        string $moduleName,
        array &$errors,
        array &$warnings
    ): void {
        $path = $moduleDir.'/module.json';

        if (! is_file($path)) {
            $errors[] = "module.json not found.";

            return;
        }

        $raw     = file_get_contents($path);
        $decoded = $raw !== false ? json_decode($raw, true) : null;

        if (! is_array($decoded)) {
            $errors[] = 'module.json contains invalid JSON.';

            return;
        }

        foreach (['name', 'version', 'providers'] as $field) {
            if (! isset($decoded[$field])) {
                $errors[] = "module.json missing required field: {$field}";
            }
        }

        if (! isset($decoded['requires']['TitanCore'])) {
            $warnings[] = "module.json does not declare a TitanCore dependency in 'requires'. Add: \"TitanCore\": \"^1.0\".";
        }
    }

    /**
     * @param  list<string>  $errors
     * @param  list<string>  $warnings
     */
    private function validateManifestFiles(
        string $moduleDir,
        array &$errors,
        array &$warnings
    ): void {
        $manifestsDir = $moduleDir.'/manifests';

        if (! is_dir($manifestsDir)) {
            return;
        }

        $manifestFiles = array_merge(
            glob($manifestsDir.'/*.json') ?: [],
            glob($manifestsDir.'/**/*.json') ?: []
        );

        foreach ($manifestFiles as $manifestFile) {
            $raw     = file_get_contents($manifestFile);
            $decoded = $raw !== false ? json_decode($raw, true) : null;

            if (! is_array($decoded)) {
                $rel      = str_replace($moduleDir.'/', '', $manifestFile);
                $errors[] = "Invalid JSON in manifest: {$rel}";
            }
        }
    }

    /**
     * @param  list<string>  $warnings
     */
    private function validateNamespace(
        string $moduleDir,
        string $moduleName,
        array &$warnings
    ): void {
        $phpFiles = glob($moduleDir.'/**/*.php') ?: [];

        foreach (array_slice($phpFiles, 0, self::MAX_NS_SAMPLE) as $file) {
            $contents = file_get_contents($file) ?: '';
            if (preg_match('/^namespace\s+([^;]+)/m', $contents, $m)) {
                $ns = trim($m[1]);
                if (! str_starts_with($ns, "Modules\\{$moduleName}")) {
                    $rel        = str_replace($moduleDir.'/', '', $file);
                    $warnings[] = "Namespace violation in {$rel}: found {$ns} (expected Modules\\{$moduleName}\\*)";
                }
            }
        }
    }

    /**
     * @param  list<string>  $warnings
     */
    private function validateDependencies(
        string $moduleDir,
        array &$warnings
    ): void {
        $path = $moduleDir.'/module.json';
        if (! is_file($path)) {
            return;
        }

        $raw     = file_get_contents($path);
        $decoded = $raw !== false ? json_decode($raw, true) : null;

        if (! is_array($decoded)) {
            return;
        }

        $base = $this->resolveModulesBase();

        foreach ((array) ($decoded['requires'] ?? []) as $dep => $constraint) {
            $depDir = $base.'/'.$dep;

            if (! is_dir($depDir)) {
                $warnings[] = "Required module '{$dep}' is not installed.";
            }
        }
    }

    /**
     * Resolve the modules base directory, supporting absolute config paths.
     */
    private function resolveModulesBase(): string
    {
        $configured = (string) config('titan-modules.path', 'Modules');

        return str_starts_with($configured, DIRECTORY_SEPARATOR)
            ? $configured
            : base_path($configured);
    }

    /**
     * @return list<string>
     */
    private function discoverModules(string $modulesBase): array
    {
        if (! is_dir($modulesBase)) {
            return [];
        }

        $modules = [];

        foreach (glob($modulesBase.'/*', GLOB_ONLYDIR) ?: [] as $dir) {
            if (is_file($dir.'/module.json')) {
                $modules[] = basename($dir);
            }
        }

        sort($modules);

        return array_slice($modules, 0, 12);
    }
}
