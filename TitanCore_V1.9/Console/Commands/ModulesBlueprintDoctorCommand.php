<?php

namespace Modules\TitanCore\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Modules\TitanCore\Support\ManifestSchemaValidator;

/**
 * Diagnoses blueprint-specific manifest issues across all modules.
 *
 * Checks for:
 *  - Missing blueprint.manifest files (warn only)
 *  - Invalid blueprint.manifest JSON / schema violations
 *  - Missing module.manifest.json
 *  - Invalid AI / billing / workflow manifest schemas
 *
 * Usage:
 *   php artisan modules:blueprint:doctor
 *   php artisan modules:blueprint:doctor --module=CleaningJobs
 *   php artisan modules:blueprint:doctor --strict
 */
class ModulesBlueprintDoctorCommand extends Command
{
    protected $signature = 'modules:blueprint:doctor
                            {--module= : Limit diagnosis to a single module by name}
                            {--strict  : Treat warnings as failures (exit 1)}';

    protected $description = 'Diagnose blueprint-specific manifest issues (schema, AI, billing, workflow manifests).';

    /** Manifest types this command specifically targets (beyond module.json). */
    private const BLUEPRINT_TYPES = [
        'blueprint.manifest',
        'ai.manifest',
        'billing.manifest',
        'workflow.manifest',
        'workflows.manifest',
        'module.manifest.json',
    ];

    public function handle(): int
    {
        $modulesBase = base_path(config('titan-modules.path', 'Modules'));

        if (! is_dir($modulesBase)) {
            $this->components->error("Modules directory not found: {$modulesBase}");

            return self::FAILURE;
        }

        $only = $this->option('module');
        $strict = (bool) $this->option('strict');

        $moduleDirs = collect(File::directories($modulesBase))
            ->when($only !== null, fn ($c) => $c->filter(
                fn ($d) => basename($d) === $only
            ))
            ->values();

        if ($only !== null && $moduleDirs->isEmpty()) {
            $this->components->error("Module '{$only}' not found in {$modulesBase}.");

            return self::FAILURE;
        }

        $this->components->info('Blueprint Manifest Doctor');
        $this->newLine();

        $validator = new ManifestSchemaValidator();
        $totalErrors = 0;
        $totalWarnings = 0;
        $totalChecked = 0;

        foreach ($moduleDirs as $moduleDir) {
            $moduleName = basename($moduleDir);
            [$errors, $warnings, $checked] = $this->diagnoseModule($moduleDir, $moduleName, $validator);
            $totalErrors += $errors;
            $totalWarnings += $warnings;
            $totalChecked += $checked;
        }

        $this->newLine();

        if ($totalErrors === 0 && $totalWarnings === 0) {
            $this->components->info(
                sprintf('All %d blueprint manifest(s) are valid across %d module(s).', $totalChecked, $moduleDirs->count())
            );
        } else {
            if ($totalErrors > 0) {
                $this->components->warn(
                    sprintf('%d error(s) and %d warning(s) found across %d manifest(s).', $totalErrors, $totalWarnings, $totalChecked)
                );
            } else {
                $this->components->warn(
                    sprintf('%d warning(s) found across %d manifest(s).', $totalWarnings, $totalChecked)
                );
            }
        }

        if ($totalErrors > 0) {
            return self::FAILURE;
        }

        if ($strict && $totalWarnings > 0) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * Diagnose all blueprint manifests for a single module.
     *
     * @return array{int, int, int} [errors, warnings, checked]
     */
    private function diagnoseModule(string $moduleDir, string $moduleName, ManifestSchemaValidator $validator): array
    {
        $errors = 0;
        $warnings = 0;
        $checked = 0;

        $files = $this->collectBlueprintManifests($moduleDir);

        if (empty($files)) {
            // No blueprint manifests — this is informational only
            $this->line("  <fg=gray>–</> <fg=cyan>{$moduleName}</>: no blueprint manifests found");

            return [0, 0, 0];
        }

        foreach ($files as $filePath) {
            $checked++;
            $result = $validator->validateFile($filePath);
            $label = $moduleName . '/' . basename(dirname($filePath)) . '/' . basename($filePath);

            if ($result->isValid() && ! $result->hasWarnings()) {
                $this->line("  <fg=green>✓</> <fg=cyan>{$label}</>");
            } elseif (! $result->isValid()) {
                $errors++;
                foreach ($result->errors() as $err) {
                    $this->line("  <fg=red>✗</> <fg=cyan>{$label}</>: {$err}");
                }
            } else {
                $warnings++;
                foreach ($result->warnings() as $warn) {
                    $this->line("  <fg=yellow>⚠</> <fg=cyan>{$label}</>: {$warn}");
                }
            }
        }

        return [$errors, $warnings, $checked];
    }

    /**
     * Collect all blueprint-related manifest files from a module directory.
     *
     * @return string[]
     */
    private function collectBlueprintManifests(string $moduleDir): array
    {
        $files = [];

        // module.manifest.json at module root
        $rootManifest = $moduleDir . '/module.manifest.json';
        if (file_exists($rootManifest)) {
            $files[] = $rootManifest;
        }

        // manifests/ sub-directory: collect blueprint, ai, billing, workflow manifests
        $manifestsDir = $moduleDir . '/manifests';
        if (is_dir($manifestsDir)) {
            foreach (glob($manifestsDir . '/*.manifest.json') ?: [] as $file) {
                $type = $this->inferBlueprintType($file);
                if ($type !== null) {
                    $files[] = $file;
                }
            }
        }

        return $files;
    }

    /**
     * Return the blueprint manifest type if the file matches, or null otherwise.
     */
    private function inferBlueprintType(string $filePath): ?string
    {
        $filename = strtolower(basename($filePath));
        $base = preg_replace('/\.json$/', '', $filename) ?? $filename;

        foreach (self::BLUEPRINT_TYPES as $type) {
            if ($base === $type || str_ends_with($base, '.' . $type) || str_ends_with($base, $type)) {
                return $type;
            }
        }

        return null;
    }
}
