<?php

namespace Modules\TitanCore\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Modules\TitanCore\Support\ManifestSchemaValidator;
use Modules\TitanCore\Support\ModuleDependencyGraph;

/**
 * Diagnoses the module dependency graph and surfaces all issues:
 *  - Missing / disabled required modules
 *  - Version constraint violations
 *  - Conflicting modules
 *  - Circular dependency cycles
 *  - Suggested safe load order
 *  - Manifest schema validation errors/warnings
 */
class ModulesDoctorCommand extends Command
{
    protected $signature = 'modules:doctor
                            {--skip-schema : Skip manifest schema validation}';

    protected $description = 'Diagnose the module dependency graph (missing deps, conflicts, cycles, load order, manifest schemas).';

    public function handle(ModuleDependencyGraph $graph): int
    {
        $graph->build();

        $this->components->info('Module Dependency Doctor');
        $this->newLine();

        $hasProblems = false;

        // ── 1. Cycle detection ────────────────────────────────────────────────
        $cycles = $graph->detectCycles();

        if (! empty($cycles)) {
            $hasProblems = true;
            $this->components->error('Circular dependencies detected:');

            foreach ($cycles as $cycle) {
                $this->line('  <fg=red>↻</> '.implode(' → ', $cycle));
            }

            $this->newLine();
        } else {
            $this->components->twoColumnDetail('<fg=green>✓ No circular dependencies</>', '');
        }

        // ── 2. Per-module dependency issues ───────────────────────────────────
        $issues = $graph->getAllIssues();

        if (! empty($issues)) {
            $hasProblems = true;
            $this->components->error('Dependency issues found:');

            foreach ($issues as $module => $result) {
                if (! empty($result['errors'])) {
                    foreach ($result['errors'] as $err) {
                        $this->line("  <fg=red>✗</> <fg=cyan>{$module}</>: {$err}");
                    }
                }

                if (! empty($result['warnings'])) {
                    foreach ($result['warnings'] as $warn) {
                        $this->line("  <fg=yellow>⚠</> <fg=cyan>{$module}</>: {$warn}");
                    }
                }
            }

            $this->newLine();
        } else {
            $this->components->twoColumnDetail('<fg=green>✓ All dependency constraints satisfied</>', '');
        }

        // ── 3. Safe-boot provider failures ────────────────────────────────────
        $bootFailures = app()->bound('titan.module_boot_failures')
            ? app('titan.module_boot_failures')
            : [];
        if ($bootFailures instanceof Collection) {
            $bootFailures = $bootFailures->all();
        }

        if (is_array($bootFailures) && ! empty($bootFailures)) {
            $hasProblems = true;
            $this->components->warn('Safe-boot provider failures detected:');

            foreach ($bootFailures as $failure) {
                $module = $failure['module'] ?? 'unknown-module';
                $provider = $failure['provider'] ?? 'unknown-provider';
                $error = $failure['error'] ?? 'unknown error';

                $this->line("  <fg=yellow>⚠</> <fg=cyan>{$module}</>: {$provider} — {$error}");
            }

            $this->newLine();
        } else {
            $this->components->twoColumnDetail('<fg=green>✓ No safe-boot provider failures</>', '');
        }

        // ── 4. Manifest schema validation ─────────────────────────────────────
        if (! $this->option('skip-schema')) {
            $schemaProblems = $this->runSchemaValidation();
            if ($schemaProblems) {
                $hasProblems = true;
            }
        }

        // ── 5. Automation handler class checks ───────────────────────────────
        if ($this->runAutomationHandlerValidation()) {
            $hasProblems = true;
        }

        // ── 6. AI manifest class checks ───────────────────────────────────────
        if ($this->runAIManifestValidation()) {
            $hasProblems = true;
        }

        // ── 7. Tenant boundary diagnostics ─────────────────────────────────────
        if ($this->runTenantBoundaryValidation()) {
            $hasProblems = true;
        }

        // ── 8. Load order ─────────────────────────────────────────────────────
        $this->newLine();
        $this->components->info('Resolved load order:');
        $order = $graph->resolveLoadOrder();
        $nodes = $graph->getNodes();

        $enabledModulesOrder = array_values(
            array_filter($order, fn (string $name) => isset($nodes[$name]) && $nodes[$name]['enabled'])
        );

        foreach ($enabledModulesOrder as $i => $name) {
            $num = str_pad((string) ($i + 1), 3, ' ', STR_PAD_LEFT);
            $this->line("  {$num}. <fg=cyan>{$name}</> [<fg=green>enabled</>]");
        }

        $this->newLine();

        if ($hasProblems) {
            $this->components->warn('Doctor found problems. Fix the issues listed above.');

            return self::FAILURE;
        }

        $this->components->info('All checks passed. Module dependency graph is healthy.');

        return self::SUCCESS;
    }

    /**
     * Run manifest schema validation across all modules.
     *
     * Returns true if any failures were found.
     */
    private function runSchemaValidation(): bool
    {
        $this->newLine();
        $this->components->info('Manifest Schema Validation:');

        $modulesBase = base_path(config('titan-modules.path', 'Modules'));

        if (! is_dir($modulesBase)) {
            $this->components->warn('Modules directory not found. Skipping schema validation.');

            return false;
        }

        $validator     = new ManifestSchemaValidator();
        $strict        = (bool) config('titan-modules.strict_manifest_validation', false);
        $hasFailures   = false;
        $hasWarnings   = false;
        $totalChecked  = 0;

        foreach (File::directories($modulesBase) as $moduleDir) {
            $moduleName = basename($moduleDir);
            $results    = $validator->validateModule($moduleDir);

            foreach ($results as $result) {
                $totalChecked++;

                if ($result->isValid() && ! $result->hasWarnings()) {
                    continue;
                }

                if (! $result->isValid()) {
                    $hasFailures = true;
                    foreach ($result->errors() as $err) {
                        $this->line("  <fg=red>✗</> <fg=cyan>{$moduleName}/{$result->label()}</>: {$err}");
                    }
                } else {
                    $hasWarnings = true;
                    foreach ($result->warnings() as $warn) {
                        $this->line("  <fg=yellow>⚠</> <fg=cyan>{$moduleName}/{$result->label()}</>: {$warn}");
                    }
                }
            }
        }

        if (! $hasFailures && ! $hasWarnings) {
            $this->components->twoColumnDetail(
                sprintf('<fg=green>✓ All %d manifest(s) valid</>', $totalChecked),
                ''
            );
        }

        if ($hasFailures && $strict) {
            $this->newLine();
            $this->components->error('Strict manifest validation is enabled. Fix all schema errors before continuing.');
        }

        return $hasFailures;
    }

    /**
     * Validate that handler classes declared in automation manifests exist.
     *
     * Returns true if any missing handlers were found.
     */
    private function runAutomationHandlerValidation(): bool
    {
        $this->newLine();
        $this->components->info('Automation Manifest Handler Validation:');

        $modulesBase = base_path(config('titan-modules.path', 'Modules'));
        if (! is_dir($modulesBase)) {
            $this->components->warn('Modules directory not found. Skipping automation handler validation.');

            return false;
        }

        $statusMap = $this->moduleStatusMap($modulesBase);
        $hasFailures = false;

        foreach (File::directories($modulesBase) as $moduleDir) {
            $moduleName = basename($moduleDir);

            if (! $this->isModuleEnabled($moduleDir, $moduleName, $statusMap)) {
                continue;
            }

            $manifestPath = $moduleDir.'/manifests/automation.manifest.json';
            if (! is_file($manifestPath)) {
                continue;
            }

            $manifest = $this->decodeJsonFile($manifestPath);
            if (! is_array($manifest) || ($manifest['enabled'] ?? true) === false) {
                continue;
            }

            foreach ((array) ($manifest['handlers'] ?? []) as $index => $handler) {
                $class = $this->resolveManifestClass($moduleName, $handler, 'Handlers');

                if ($class === null || class_exists($class)) {
                    continue;
                }

                $hasFailures = true;
                $entryLabel = is_int($index) ? "handlers[{$index}]" : "handlers.{$index}";
                $this->line("  <fg=red>✗</> <fg=cyan>{$moduleName}/manifests/automation.manifest.json ({$entryLabel})</>: missing handler class {$class}");
            }
        }

        if (! $hasFailures) {
            $this->components->twoColumnDetail('<fg=green>✓ All declared automation handlers resolve</>', '');
        }

        return $hasFailures;
    }

    private function runTenantBoundaryValidation(): bool
    {
        $this->newLine();
        $this->components->info('Tenant Boundary Validation:');

        if (! Schema::hasTable('driver_locations')) {
            $this->components->warn('driver_locations table not found. Skipping technician location tenant boundary checks.');

            return false;
        }

        if (Schema::hasColumn('driver_locations', 'organization_id')) {
            $this->components->twoColumnDetail('<fg=green>✓ driver_locations has organization_id for org-scoped technician location queries</>', '');

            return false;
        }

        $this->line('  <fg=red>✗</> <fg=cyan>driver_locations</>: missing organization_id column required for org-scoped technician location queries');

        return true;
    }

    /**
     * Validate that agent and tool handler classes declared in AI manifests exist.
     *
     * Checks:
     *  - `manifests/ai.manifest.json` → agents[] and tools[] class references
     *  - Agent manifests under Agents/ → agent_class references
     *
     * Returns true if any missing classes were found.
     */
    private function runAIManifestValidation(): bool
    {
        $this->newLine();
        $this->components->info('AI Manifest Class Validation:');

        $modulesBase = base_path(config('titan-modules.path', 'Modules'));
        if (! is_dir($modulesBase)) {
            $this->components->warn('Modules directory not found. Skipping AI manifest validation.');

            return false;
        }

        $statusMap   = $this->moduleStatusMap($modulesBase);
        $hasFailures = false;

        foreach (File::directories($modulesBase) as $moduleDir) {
            $moduleName = basename($moduleDir);

            if (! $this->isModuleEnabled($moduleDir, $moduleName, $statusMap)) {
                continue;
            }

            // ── Check manifests/ai.manifest.json ──────────────────────────────
            $aiManifestPath = $moduleDir.'/manifests/ai.manifest.json';
            if (is_file($aiManifestPath)) {
                $aiManifest = $this->decodeJsonFile($aiManifestPath);

                if (is_array($aiManifest) && ($aiManifest['enabled'] ?? true) !== false) {
                    foreach ((array) ($aiManifest['agents'] ?? []) as $index => $agent) {
                        $class = is_string($agent)
                            ? $agent
                            : (is_array($agent) ? ($agent['agent_class'] ?? $agent['class'] ?? null) : null);

                        if (is_string($class) && $class !== '' && ! class_exists($class)) {
                            $hasFailures = true;
                            $entryLabel  = "agents[{$index}]";
                            $this->line("  <fg=red>✗</> <fg=cyan>{$moduleName}/manifests/ai.manifest.json ({$entryLabel})</>: missing agent class {$class}");
                        }
                    }

                    foreach ((array) ($aiManifest['tools'] ?? []) as $index => $tool) {
                        $class = null;

                        if (is_string($tool)) {
                            $class = $tool;
                        } elseif (is_array($tool)) {
                            $class = $tool['class'] ?? $tool['handler'] ?? null;
                        }

                        if (is_string($class) && $class !== '' && str_contains($class, '\\') && ! class_exists($class)) {
                            $hasFailures = true;
                            $entryLabel  = "tools[{$index}]";
                            $this->line("  <fg=red>✗</> <fg=cyan>{$moduleName}/manifests/ai.manifest.json ({$entryLabel})</>: missing tool class {$class}");
                        }
                    }
                }
            }

            // ── Check Agents/*/agent.manifest.json agent_class references ─────
            $agentsDir = $moduleDir.'/Agents';
            if (is_dir($agentsDir)) {
                foreach (glob($agentsDir.'/*/agent.manifest.json') ?: [] as $agentManifestPath) {
                    $agentManifest = $this->decodeJsonFile($agentManifestPath);
                    if (! is_array($agentManifest)) {
                        continue;
                    }

                    $agentClass = $agentManifest['agent_class'] ?? null;
                    if (is_string($agentClass) && $agentClass !== '' && ! class_exists($agentClass)) {
                        $hasFailures = true;
                        $relPath     = str_replace($moduleDir.'/', '', $agentManifestPath);
                        $this->line("  <fg=red>✗</> <fg=cyan>{$moduleName}/{$relPath} (agent_class)</>: missing agent class {$agentClass}");
                    }
                }
            }
        }

        if (! $hasFailures) {
            $this->components->twoColumnDetail('<fg=green>✓ All declared AI agent and tool classes resolve</>', '');
        }

        return $hasFailures;
    }

    /**
     * @param  array<string, bool>  $statusMap
     */
    private function isModuleEnabled(string $moduleDir, string $moduleName, array $statusMap): bool
    {
        if (array_key_exists($moduleName, $statusMap) && $statusMap[$moduleName] === false) {
            return false;
        }

        $moduleJsonPath = $moduleDir.'/module.json';
        if (! is_file($moduleJsonPath)) {
            return true;
        }

        $moduleJson = $this->decodeJsonFile($moduleJsonPath);
        if (! is_array($moduleJson)) {
            return true;
        }

        if (array_key_exists('active', $moduleJson) && (int) $moduleJson['active'] === 0) {
            return false;
        }

        return ! (array_key_exists('enabled', $moduleJson) && $moduleJson['enabled'] === false);
    }

    /**
     * @return array<string, bool>
     */
    private function moduleStatusMap(string $modulesBase): array
    {
        $statusFiles = [
            dirname($modulesBase).'/module_statuses.json',
            dirname($modulesBase).'/modules_statuses.json',
        ];

        foreach ($statusFiles as $statusFile) {
            if (! is_file($statusFile)) {
                continue;
            }

            $decoded = $this->decodeJsonFile($statusFile);
            if (! is_array($decoded)) {
                return [];
            }

            return array_map(fn (mixed $value): bool => (bool) $value, $decoded);
        }

        return [];
    }

    /**
     * @param  mixed  $entry
     */
    private function resolveManifestClass(string $moduleName, mixed $entry, string $defaultSubNamespace): ?string
    {
        if (is_string($entry) && $entry !== '') {
            return str_contains($entry, '\\')
                ? $entry
                : "Modules\\{$moduleName}\\Automation\\{$defaultSubNamespace}\\{$entry}";
        }

        if (! is_array($entry)) {
            return null;
        }

        $candidate = $entry['class'] ?? $entry['key'] ?? $entry['id'] ?? $entry['name'] ?? null;
        if (! is_string($candidate) || $candidate === '') {
            return null;
        }

        return str_contains($candidate, '\\')
            ? $candidate
            : "Modules\\{$moduleName}\\Automation\\{$defaultSubNamespace}\\{$candidate}";
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeJsonFile(string $path): ?array
    {
        if (! is_file($path)) {
            return null;
        }

        $raw = file_get_contents($path);
        if ($raw === false) {
            return null;
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : null;
    }
}
