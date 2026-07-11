<?php

namespace Modules\TitanCore\Console\Commands\Mdk;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * SDK migration tool — helps modules upgrade to the latest TitanSDK contracts.
 *
 * Usage:
 *   php artisan titan:migrate-sdk
 *   php artisan titan:migrate-sdk --module=CRM
 *   php artisan titan:migrate-sdk --dry-run
 *
 * Currently detects:
 *  - Direct TitanCore internal class imports (should use contracts instead)
 *  - Deprecated manifest fields
 *  - Missing module.json `requires.TitanCore` declarations
 *
 * Exit codes:
 *   0 — no migrations needed
 *   1 — migrations or issues were found
 */
class MigrateSdkCommand extends Command
{
    protected $signature = 'titan:migrate-sdk
                            {--module= : Limit scan to a specific module}
                            {--dry-run : Report issues without modifying files}';

    protected $description = 'Detect and report SDK migration issues across Titan modules.';

    /** TitanCore internal namespaces that external modules must not import. */
    private const INTERNAL_NAMESPACES = [
        'Modules\\TitanCore\\Services\\',
        'Modules\\TitanCore\\Repositories\\',
        'Modules\\TitanCore\\Models\\',
        'Modules\\TitanCore\\Http\\',
        'Modules\\TitanCore\\Database\\',
    ];

    /** Fields deprecated in module.json. */
    private const DEPRECATED_MODULE_JSON_FIELDS = [
        'autoload',
        'active' => 'Use the "enabled" field instead of "active".',
    ];

    public function handle(): int
    {
        $modulesBase = $this->resolveModulesBase();
        $onlyModule  = $this->option('module');
        $isDryRun    = (bool) $this->option('dry-run');

        $this->components->info(
            ($isDryRun ? '[DRY-RUN] ' : '').'SDK Migration Scanner'
        );
        $this->newLine();

        if (! is_dir($modulesBase)) {
            $this->components->error("Modules directory not found: {$modulesBase}");

            return self::FAILURE;
        }

        $moduleDirs = collect(File::directories($modulesBase))
            ->when($onlyModule !== null, fn ($c) => $c->filter(
                fn ($d) => basename($d) === $onlyModule
            ))
            ->values();

        if ($onlyModule !== null && $moduleDirs->isEmpty()) {
            $this->components->error("Module '{$onlyModule}' not found.");

            return self::FAILURE;
        }

        $totalIssues = 0;

        foreach ($moduleDirs as $moduleDir) {
            $moduleName = basename($moduleDir);

            // Skip TitanCore itself.
            if ($moduleName === 'TitanCore') {
                continue;
            }

            $issues = array_merge(
                $this->scanInternalImports($moduleDir, $moduleName),
                $this->scanDeprecatedModuleJson($moduleDir, $moduleName),
                $this->scanMissingTitanCoreRequirement($moduleDir, $moduleName)
            );

            if (! empty($issues)) {
                $this->components->warn("<fg=cyan>{$moduleName}</> — ".count($issues).' issue(s):');
                foreach ($issues as $issue) {
                    $this->line("  <fg=yellow>⚠</> {$issue}");
                }
                $this->newLine();
                $totalIssues += count($issues);
            }
        }

        if ($totalIssues === 0) {
            $this->components->info('No SDK migration issues found.');

            return self::SUCCESS;
        }

        $this->components->warn(
            "Found {$totalIssues} issue(s) across scanned modules. "
            .($isDryRun ? 'No files were modified (dry-run).' : 'Review and fix manually.')
        );

        return self::FAILURE;
    }

    // ──────────────────────────────────────────────────────────────────────────

    /**
     * @return list<string>
     */
    private function scanInternalImports(string $moduleDir, string $moduleName): array
    {
        $issues   = [];
        $phpFiles = glob($moduleDir.'/**/*.php') ?: [];

        foreach ($phpFiles as $file) {
            $contents = file_get_contents($file) ?: '';
            $rel      = str_replace($moduleDir.'/', '', $file);

            foreach (self::INTERNAL_NAMESPACES as $internalNs) {
                if (str_contains($contents, "use {$internalNs}") || str_contains($contents, "new \\{$internalNs}")) {
                    $issues[] = "{$rel}: imports internal TitanCore namespace '{$internalNs}*'. Use a contract instead.";
                }
            }
        }

        return $issues;
    }

    /**
     * @return list<string>
     */
    private function scanDeprecatedModuleJson(string $moduleDir, string $moduleName): array
    {
        $path = $moduleDir.'/module.json';
        if (! is_file($path)) {
            return [];
        }

        $raw     = file_get_contents($path);
        $decoded = $raw !== false ? json_decode($raw, true) : null;

        if (! is_array($decoded)) {
            return [];
        }

        $issues = [];

        foreach (self::DEPRECATED_MODULE_JSON_FIELDS as $field => $hint) {
            $actualField = is_int($field) ? $hint : $field;
            $message     = is_string($hint) && ! is_int($field) ? $hint : "Field '{$actualField}' is deprecated in module.json.";

            if (array_key_exists($actualField, $decoded)) {
                $issues[] = "module.json: {$message}";
            }
        }

        return $issues;
    }

    /**
     * @return list<string>
     */
    private function scanMissingTitanCoreRequirement(string $moduleDir, string $moduleName): array
    {
        $path = $moduleDir.'/module.json';
        if (! is_file($path)) {
            return [];
        }

        $raw     = file_get_contents($path);
        $decoded = $raw !== false ? json_decode($raw, true) : null;

        if (! is_array($decoded)) {
            return [];
        }

        if (! isset($decoded['requires']['TitanCore'])) {
            return ["module.json: missing 'requires.TitanCore' dependency declaration. Add: \"TitanCore\": \"^1.0\"."];
        }

        return [];
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
}
