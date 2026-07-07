<?php

namespace Modules\TitanCore\Console\Commands;

use Illuminate\Console\Command;
use Modules\TitanCore\Support\ModuleDependencyGraph;

/**
 * Shows the full dependency tree for a given module, including:
 *  - Titan module dependencies (recursive)
 *  - Composer package dependencies
 *  - Enabled/disabled status of each dependency
 *  - Circular reference markers
 */
class ModulesDepsCommand extends Command
{
    protected $signature = 'modules:deps {module : The module name to inspect}';

    protected $description = 'Show the full dependency tree for a module.';

    public function handle(ModuleDependencyGraph $graph): int
    {
        $moduleName = $this->argument('module');

        $graph->build();

        $nodes = $graph->getNodes();

        if (! isset($nodes[$moduleName])) {
            $this->components->error("Module '{$moduleName}' is not installed.");

            return self::FAILURE;
        }

        $this->components->info("Dependency tree for <fg=cyan>{$moduleName}</>");
        $this->newLine();

        $tree = $graph->getDependencyTree($moduleName);
        $this->renderTree($tree);

        // ── Validation summary ────────────────────────────────────────────────
        $this->newLine();
        $result = $graph->validateEnableModule($moduleName);

        if (! empty($result['errors'])) {
            $this->components->error('This module cannot be enabled:');
            foreach ($result['errors'] as $err) {
                $this->line("  <fg=red>✗</> {$err}");
            }
        }

        if (! empty($result['warnings'])) {
            $this->components->warn('Warnings:');
            foreach ($result['warnings'] as $warn) {
                $this->line("  <fg=yellow>⚠</> {$warn}");
            }
        }

        if (empty($result['errors']) && empty($result['warnings'])) {
            $this->components->twoColumnDetail('<fg=green>✓ All dependencies satisfied</>', '');
        }

        return empty($result['errors']) ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Recursively render the dependency tree node.
     */
    protected function renderTree(array $node, string $prefix = '', bool $isLast = true): void
    {
        $connector = $isLast ? '└── ' : '├── ';
        $childPfx = $isLast ? '    ' : '│   ';

        $name = $node['name'];
        $version = $node['version'] !== ModuleDependencyGraph::DEFAULT_VERSION ? " <fg=gray>v{$node['version']}</>" : '';
        $enabled = ($node['enabled'] ?? false) ? '<fg=green>✓</>' : '<fg=red>✗</>';
        $extra = '';

        if (! empty($node['missing'])) {
            $extra = ' <fg=red>[MISSING]</>';
        } elseif (! empty($node['circular'])) {
            $extra = ' <fg=yellow>[circular]</>';
        }

        if ($node['depth'] === 0) {
            $this->line("<fg=cyan;options=bold>{$name}</>{$version} [{$enabled}]");
        } else {
            $this->line("{$prefix}{$connector}<fg=cyan>{$name}</>{$version} [{$enabled}]{$extra}");
        }

        // Composer deps
        foreach ($node['composer_deps'] ?? [] as $composerDep) {
            $constraint = $composerDep['constraint'] ? " ({$composerDep['constraint']})" : '';
            $this->line("{$prefix}{$childPfx}└── <fg=gray>{$composerDep['name']}{$constraint}</> [composer]");
        }

        $children = $node['children'] ?? [];
        $count = count($children);

        foreach ($children as $i => $child) {
            $last = ($i === $count - 1);
            $this->renderTree($child, $prefix.$childPfx, $last);
        }
    }
}
