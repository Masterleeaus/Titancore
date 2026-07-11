<?php

namespace Modules\TitanCore\Console\Commands;

use Illuminate\Console\Command;
use Modules\TitanCore\Services\ModulePersistence\TitanModuleLifecycleStore;
use Modules\TitanCore\Support\ModuleDependencyGraph;
use Nwidart\Modules\Module;

/**
 * Enable one or more modules after validating that all dependency constraints
 * are satisfied.
 *
 * Blocks enabling when:
 *  - A required module is not installed.
 *  - A required module is currently disabled.
 *  - A semver version constraint in `requires` is not met.
 *  - A `conflicts` entry is currently enabled.
 *
 * Use --force to skip validation and enable unconditionally.
 */
class ModulesEnableCommand extends Command
{
    protected $signature = 'modules:enable
                            {module* : One or more module names to enable}
                            {--force : Skip dependency validation and enable unconditionally}';

    protected $description = 'Enable module(s) after validating dependency constraints.';

    public function handle(ModuleDependencyGraph $graph, TitanModuleLifecycleStore $lifecycleStore): int
    {
        /** @var string[] $moduleNames */
        $moduleNames = (array) $this->argument('module');
        $force = (bool) $this->option('force');
        $exitCode = self::SUCCESS;

        $graph->build();

        foreach ($moduleNames as $moduleName) {
            $result = $this->enableModule($moduleName, $graph, $force, $lifecycleStore);

            if ($result !== self::SUCCESS) {
                $exitCode = self::FAILURE;
            }
        }

        return $exitCode;
    }

    protected function enableModule(string $moduleName, ModuleDependencyGraph $graph, bool $force, TitanModuleLifecycleStore $lifecycleStore): int
    {
        // Resolve module from nwidart repository
        /** @var Module|null $module */
        $module = $this->laravel['modules']->find($moduleName);

        if ($module === null) {
            $this->components->error("Module '{$moduleName}' is not installed.");

            return self::FAILURE;
        }

        if ($module->isEnabled()) {
            $this->components->twoColumnDetail(
                "<fg=cyan>{$moduleName}</>",
                '<fg=green>Already enabled</>'
            );

            return self::SUCCESS;
        }

        // ── Dependency validation ─────────────────────────────────────────────
        if (! $force) {
            $validation = $graph->validateEnableModule($moduleName);

            if (! empty($validation['errors'])) {
                $this->components->error("Cannot enable '{$moduleName}' – dependency check failed:");
                foreach ($validation['errors'] as $err) {
                    $this->line("  <fg=red>✗</> {$err}");
                }

                if (! empty($validation['warnings'])) {
                    foreach ($validation['warnings'] as $warn) {
                        $this->line("  <fg=yellow>⚠</> {$warn}");
                    }
                }

                $this->line("  <fg=gray>Tip: run `php artisan modules:deps {$moduleName}` to see the full dependency tree.</>");

                return self::FAILURE;
            }

            if (! empty($validation['warnings'])) {
                $this->components->warn("Warnings for '{$moduleName}':");
                foreach ($validation['warnings'] as $warn) {
                    $this->line("  <fg=yellow>⚠</> {$warn}");
                }
            }
        }

        // ── Enable the module ─────────────────────────────────────────────────
        $this->components->task(
            "Enabling <fg=cyan>{$moduleName}</>",
            function () use ($module) {
                $module->enable();
            }
        );

        $lifecycleStore->markEnabled(
            $moduleName,
            is_string($module->get('version')) ? $module->get('version') : null
        );

        return self::SUCCESS;
    }
}
