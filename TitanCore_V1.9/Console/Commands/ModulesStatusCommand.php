<?php

namespace Modules\TitanCore\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Nwidart\Modules\Module;

/**
 * Displays a summary table of all discovered modules and their current status.
 *
 * Columns:
 *  - Module     : directory / class name
 *  - Version    : version from module.json (or '—' if not declared)
 *  - Installed  : YES / NO (module.json present)
 *  - Status     : enabled / disabled (from nwidart module repository)
 *  - Description: one-line description from module.json
 *
 * Usage:
 *   php artisan modules:status
 *   php artisan modules:status --enabled   # show only enabled modules
 *   php artisan modules:status --disabled  # show only disabled modules
 *   php artisan modules:status --json      # machine-readable output
 */
class ModulesStatusCommand extends Command
{
    protected $signature = 'modules:status
                            {--enabled  : Show only enabled modules}
                            {--disabled : Show only disabled modules}
                            {--json     : Output results as JSON}';

    protected $description = 'Display enabled / disabled / installed status for all modules.';

    /** Maximum description length before truncation. */
    private const DESCRIPTION_MAX_LENGTH = 60;

    public function handle(): int
    {
        $modulesBase = base_path(config('titan-modules.path', 'Modules'));

        if (! is_dir($modulesBase)) {
            $this->components->error("Modules directory not found: {$modulesBase}");

            return self::FAILURE;
        }

        $rows = $this->collectRows($modulesBase);

        // Apply filters
        if ($this->option('enabled')) {
            $rows = array_filter($rows, fn ($r) => $r['status'] === 'enabled');
        } elseif ($this->option('disabled')) {
            $rows = array_filter($rows, fn ($r) => $r['status'] === 'disabled');
        }

        $rows = array_values($rows);

        if (empty($rows)) {
            $this->warn('No modules found matching the given filters.');

            return self::SUCCESS;
        }

        if ($this->option('json')) {
            $this->line(json_encode($rows, JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }

        $this->renderTable($rows);

        return self::SUCCESS;
    }

    // ──────────────────────────────────────────────────────────────────────────

    /**
     * @return array<int, array{module: string, version: string, installed: string, status: string, description: string}>
     */
    private function collectRows(string $modulesBase): array
    {
        $rows = [];

        foreach (File::directories($modulesBase) as $moduleDir) {
            $moduleName = basename($moduleDir);
            $manifestPath = $moduleDir . '/module.json';

            if (! file_exists($manifestPath)) {
                $rows[] = [
                    'module'      => $moduleName,
                    'version'     => '—',
                    'installed'   => 'NO',
                    'status'      => $this->resolveStatus($moduleName),
                    'description' => '<no module.json>',
                ];
                continue;
            }

            $manifest = json_decode(file_get_contents($manifestPath), true) ?? [];

            $rows[] = [
                'module'      => $moduleName,
                'version'     => (string) ($manifest['version'] ?? '—'),
                'installed'   => 'YES',
                'status'      => $this->resolveStatus($moduleName),
                'description' => (string) ($manifest['description'] ?? ''),
            ];
        }

        usort($rows, fn ($a, $b) => strcmp($a['module'], $b['module']));

        return $rows;
    }

    /**
     * Determine whether a module is enabled or disabled using the nwidart repository.
     * Falls back to 'unknown' if the module is not registered in the nwidart registry.
     */
    private function resolveStatus(string $moduleName): string
    {
        try {
            /** @var Module|null $module */
            $module = $this->laravel['modules']->find($moduleName);

            if ($module === null) {
                return 'unknown';
            }

            return $module->isEnabled() ? 'enabled' : 'disabled';
        } catch (\Throwable) {
            return 'unknown';
        }
    }

    /**
     * @param array<int, array{module: string, version: string, installed: string, status: string, description: string}> $rows
     */
    private function renderTable(array $rows): void
    {
        // Summary counts
        $enabled  = count(array_filter($rows, fn ($r) => $r['status'] === 'enabled'));
        $disabled = count(array_filter($rows, fn ($r) => $r['status'] === 'disabled'));
        $total    = count($rows);

        $this->components->info("Module Status ({$total} total, {$enabled} enabled, {$disabled} disabled)");
        $this->newLine();

        $headers = ['Module', 'Version', 'Installed', 'Status', 'Description'];

        $tableRows = array_map(function (array $row): array {
            $statusColor = match ($row['status']) {
                'enabled'  => '<fg=green>enabled</>',
                'disabled' => '<fg=red>disabled</>',
                default    => '<fg=yellow>unknown</>',
            };

            $installedColor = $row['installed'] === 'YES'
                ? '<fg=green>YES</>'
                : '<fg=red>NO</>';

            // Truncate long descriptions
            $desc = mb_strlen($row['description']) > self::DESCRIPTION_MAX_LENGTH
                ? mb_substr($row['description'], 0, self::DESCRIPTION_MAX_LENGTH - 3) . '...'
                : $row['description'];

            return [
                $row['module'],
                $row['version'],
                $installedColor,
                $statusColor,
                $desc,
            ];
        }, $rows);

        $this->table($headers, $tableRows);
    }
}
