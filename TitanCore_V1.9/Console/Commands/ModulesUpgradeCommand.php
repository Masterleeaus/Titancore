<?php

namespace Modules\TitanCore\Console\Commands;

use Illuminate\Console\Command;
use Modules\TitanCore\Services\Upgrade\DependencyChecker;
use Modules\TitanCore\Services\Upgrade\PreUpgradeBackupJob;
use Modules\TitanCore\Services\Upgrade\UpgradeEngine;
use Modules\TitanCore\Services\Upgrade\UpgradeRollbackRunner;
use Modules\TitanCore\Services\Upgrade\VersionCompatibilityChecker;

/**
 * Upgrade one or all Titan modules with production-safety guarantees:
 *   --dry-run           Show what would change without writing anything
 *   --no-maintenance    Skip artisan down/up around the upgrade
 *   --no-backup         Skip pre-upgrade snapshot (not recommended)
 *   --rollback=<dir>    Immediately rollback a previous snapshot instead of upgrading
 */
class ModulesUpgradeCommand extends Command
{
    protected $signature = 'modules:upgrade
                            {module? : Module name (e.g. CleaningJobs). Omit to upgrade all.}
                            {--dry-run : Output a diff of what will change without writing}
                            {--no-maintenance : Skip maintenance mode (artisan down/up)}
                            {--no-backup : Skip pre-upgrade backup snapshot}
                            {--rollback= : Path to a snapshot directory to roll back from}';

    protected $description = 'Upgrade one or all modules with backup, rollback, and health-check safety.';

    public function handle(): int
    {
        // ── Rollback-only mode ────────────────────────────────────────────────
        if ($snapshotDir = $this->option('rollback')) {
            return $this->runRollback((string) $snapshotDir);
        }

        $moduleName  = $this->argument('module');
        $isDryRun    = (bool) $this->option('dry-run');
        $maintenance = ! $this->option('no-maintenance');
        $backup      = ! $this->option('no-backup');

        $targets = $moduleName
            ? [$moduleName]
            : $this->discoverModules();

        if (empty($targets)) {
            $this->warn('No modules found to upgrade.');
            return self::SUCCESS;
        }

        $engine = $this->buildEngine($isDryRun, $maintenance, $backup);

        $allOk = true;
        foreach ($targets as $target) {
            $ok = $this->upgradeModule($engine, $target, $isDryRun);
            if (! $ok) {
                $allOk = false;
            }
        }

        return $allOk ? self::SUCCESS : self::FAILURE;
    }

    // ──────────────────────────────────────────────────────────────────────────

    private function upgradeModule(UpgradeEngine $engine, string $moduleName, bool $isDryRun): bool
    {
        $this->info('');
        $this->line(str_repeat('─', 60));
        $this->info(($isDryRun ? '[DRY-RUN] ' : '') . "Upgrading module: <comment>{$moduleName}</comment>");
        $this->line(str_repeat('─', 60));

        if (! is_dir(base_path("Modules/{$moduleName}"))) {
            $this->error("Module directory not found: Modules/{$moduleName}");
            return false;
        }

        $result = $engine->run($moduleName);

        // Print step table
        $rows = array_map(fn($s) => [$s['step'], $s['status'], $this->truncate($s['detail'], 80)], $result['steps']);
        $this->table(['Step', 'Status', 'Detail'], $rows);

        if ($result['snapshot_dir']) {
            $this->line("  Snapshot: <fg=cyan>{$result['snapshot_dir']}</>");
        }

        if (! $result['ok']) {
            foreach ($result['errors'] as $err) {
                $this->error($err);
            }
            $this->error("Module <comment>{$moduleName}</comment> upgrade FAILED.");
            return false;
        }

        $this->info("Module <comment>{$moduleName}</comment> upgrade " . ($isDryRun ? 'dry-run completed ✓' : 'succeeded ✓'));
        return true;
    }

    private function runRollback(string $snapshotDir): int
    {
        $this->info("Rolling back from snapshot: <comment>{$snapshotDir}</comment>");
        $rollback = new UpgradeRollbackRunner();
        $result   = $rollback->rollback($snapshotDir);

        $this->line('Restored tables : ' . implode(', ', $result['restored_tables'] ?: ['(none)']));
        $this->line('Restored files  : ' . $result['restored_files']);

        if (! empty($result['errors'])) {
            foreach ($result['errors'] as $err) {
                $this->error($err);
            }
        }

        if ($result['ok']) {
            $this->info('Rollback completed successfully ✓');
            return self::SUCCESS;
        }

        $this->error('Rollback completed with errors.');
        return self::FAILURE;
    }

    private function buildEngine(bool $dryRun, bool $maintenance, bool $withBackup): UpgradeEngine
    {
        $backup = $withBackup
            ? new PreUpgradeBackupJob()
            : new class extends PreUpgradeBackupJob {
                public function run(string $moduleName, string $modulePath, array $tables = []): string
                {
                    return '(backup skipped)';
                }
            };

        return (new UpgradeEngine(
            new VersionCompatibilityChecker(),
            new DependencyChecker(),
            $backup,
            new UpgradeRollbackRunner()
        ))
            ->setDryRun($dryRun)
            ->setMaintenanceMode($maintenance);
    }

    /**
     * Discover all module directories that have a module.json.
     *
     * @return string[]
     */
    private function discoverModules(): array
    {
        $base    = base_path('Modules');
        $modules = [];

        foreach (glob($base . '/*/module.json') ?: [] as $manifest) {
            $modules[] = basename(dirname($manifest));
        }

        sort($modules);
        return $modules;
    }

    private function truncate(string $str, int $len): string
    {
        return strlen($str) > $len ? substr($str, 0, $len - 3) . '...' : $str;
    }
}
