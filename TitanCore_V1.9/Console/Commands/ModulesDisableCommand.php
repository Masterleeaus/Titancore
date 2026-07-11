<?php

namespace Modules\TitanCore\Console\Commands;

use Illuminate\Console\Command;
use Modules\TitanCore\Services\ModulePersistence\TitanModuleLifecycleStore;
use Modules\TitanCore\Support\ModuleDependencyGraph;
use Nwidart\Modules\Module;

/**
 * Disable one or more modules.
 *
 * Blocks disabling when the module is required by another currently-enabled
 * module, unless --force is passed.
 *
 * Usage:
 *   php artisan modules:disable CleaningJobs
 *   php artisan modules:disable CleaningJobs HRCore --force
 *
 * Exit codes:
 *   0 — all requested modules disabled (or already disabled)
 *   1 — one or more modules could not be disabled
 */
class ModulesDisableCommand extends Command
{
    protected $signature = 'modules:disable
                            {module* : One or more module names to disable}
                            {--force : Skip dependant-module check and disable unconditionally}';

    protected $description = 'Disable module(s) and prevent them from booting on the next request.';

    public function handle(ModuleDependencyGraph $graph, TitanModuleLifecycleStore $lifecycleStore): int
    {
        /** @var string[] $moduleNames */
        $moduleNames = (array) $this->argument('module');
        $force = (bool) $this->option('force');
        $exitCode = self::SUCCESS;

        $graph->build();

        foreach ($moduleNames as $moduleName) {
            $result = $this->disableModule($moduleName, $graph, $force, $lifecycleStore);

            if ($result !== self::SUCCESS) {
                $exitCode = self::FAILURE;
            }
        }

        return $exitCode;
    }

    // ──────────────────────────────────────────────────────────────────────────

    protected function disableModule(string $moduleName, ModuleDependencyGraph $graph, bool $force, TitanModuleLifecycleStore $lifecycleStore): int
    {
        /** @var Module|null $module */
        $module = $this->laravel['modules']->find($moduleName);

        if ($module === null) {
            $this->components->error("Module '{$moduleName}' is not installed.");

            return self::FAILURE;
        }

        if (! $module->isEnabled()) {
            $this->components->twoColumnDetail(
                "<fg=cyan>{$moduleName}</>",
                '<fg=yellow>Already disabled</>'
            );

            return self::SUCCESS;
        }

        // ── Dependant-module check ────────────────────────────────────────────
        if (! $force) {
            $dependants = $this->findEnabledDependants($moduleName, $graph);

            if (! empty($dependants)) {
                $this->components->error(
                    "Cannot disable '{$moduleName}' -- the following enabled module(s) depend on it:"
                );

                foreach ($dependants as $dep) {
                    $this->line("  <fg=red>↳</> {$dep}");
                }

                $this->line(
                    "  <fg=gray>Tip: disable dependent modules first, or run with --force to override.</>"
                );

                return self::FAILURE;
            }
        }

        // ── Disable the module ────────────────────────────────────────────────
        $this->components->task(
            "Disabling <fg=cyan>{$moduleName}</>",
            function () use ($module): void {
                $module->disable();
            }
        );

        $lifecycleStore->markDisabled($moduleName);

        return self::SUCCESS;
    }

    /**
     * Return the names of currently-enabled modules that declare $moduleName
     * as a required dependency.
     *
     * @return string[]
     */
    private function findEnabledDependants(string $moduleName, ModuleDependencyGraph $graph): array
    {
        $nodes = $graph->getNodes();
        $dependants = [];

        foreach ($nodes as $name => $node) {
            if ($name === $moduleName) {
                continue;
            }

            if (! ($node['enabled'] ?? false)) {
                continue;
            }

            // $node['requires'] is list<array{name: string, constraint: string|null}>
            $requireNames = array_column($node['requires'] ?? [], 'name');
            if (in_array($moduleName, $requireNames, true)) {
                $dependants[] = $name;
            }
        }

        return $dependants;
    }
}
