<?php

namespace Modules\TitanCore\Services\Upgrade;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Modules\TitanCore\Services\ModulePersistence\TitanModuleUpgradeTrackingStore;

/**
 * Core upgrade execution pipeline.
 *
 * Responsibilities:
 *  - Load module manifest and validate version/dependency constraints
 *  - Optionally enable maintenance mode
 *  - Run PreUpgradeBackupJob
 *  - Discover and execute upgrade files (Upgrade/Migrations, Upgrade/Scripts)
 *    each in their own DB transaction
 *  - Run post-upgrade health check; trigger rollback on failure
 *  - Disable maintenance mode
 *  - Record upgrade history
 */
class UpgradeEngine
{
    private VersionCompatibilityChecker $versionChecker;
    private DependencyChecker $dependencyChecker;
    private PreUpgradeBackupJob $backup;
    private UpgradeRollbackRunner $rollback;
    private TitanModuleUpgradeTrackingStore $upgradeTracking;

    /** When true every step is reported but no writes occur. */
    private bool $dryRun = false;

    /** Whether to call artisan down/up around the upgrade. */
    private bool $maintenanceMode = true;

    public function __construct(
        ?VersionCompatibilityChecker $versionChecker = null,
        ?DependencyChecker           $dependencyChecker = null,
        ?PreUpgradeBackupJob         $backup = null,
        ?UpgradeRollbackRunner       $rollback = null,
        ?TitanModuleUpgradeTrackingStore $upgradeTracking = null,
    ) {
        $this->versionChecker    = $versionChecker    ?? new VersionCompatibilityChecker();
        $this->dependencyChecker = $dependencyChecker ?? new DependencyChecker();
        $this->backup            = $backup            ?? new PreUpgradeBackupJob();
        $this->rollback          = $rollback          ?? new UpgradeRollbackRunner();
        $this->upgradeTracking   = $upgradeTracking   ?? new TitanModuleUpgradeTrackingStore();
    }

    public function setDryRun(bool $dryRun): static
    {
        $this->dryRun = $dryRun;
        return $this;
    }

    public function setMaintenanceMode(bool $enabled): static
    {
        $this->maintenanceMode = $enabled;
        return $this;
    }

    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Run the full upgrade pipeline for a single module.
     *
     * @param  string  $moduleName  e.g. "CleaningJobs"
     * @return array{
     *   ok: bool,
     *   dry_run: bool,
     *   module: string,
     *   steps: array<int, array{step: string, status: string, detail: string}>,
     *   snapshot_dir: string|null,
     *   errors: string[],
     * }
     */
    public function run(string $moduleName): array
    {
        $modulePath  = base_path('Modules/' . $moduleName);
        $manifest    = $this->loadManifest($modulePath);
        $steps       = [];
        $errors      = [];
        $snapshotDir = null;

        // ── 1. Version compatibility check ────────────────────────────────────
        $vcResult = $this->versionChecker->check($manifest);
        $steps[]  = $this->step('version_check', $vcResult['ok'] ? 'passed' : 'failed', implode('; ', $vcResult['errors']));
        if (! $vcResult['ok']) {
            $errors = array_merge($errors, $vcResult['errors']);
            return $this->result(false, $moduleName, $steps, $snapshotDir, $errors);
        }

        // ── 2. Dependency check ───────────────────────────────────────────────
        $depResult = $this->dependencyChecker->check($manifest);
        $steps[]   = $this->step('dependency_check', $depResult['ok'] ? 'passed' : 'failed', implode('; ', $depResult['errors']));
        if (! $depResult['ok']) {
            $errors = array_merge($errors, $depResult['errors']);
            return $this->result(false, $moduleName, $steps, $snapshotDir, $errors);
        }

        // ── 3. Discover upgrade files ─────────────────────────────────────────
        $upgradeFiles = $this->discoverUpgradeFiles($modulePath);
        if (empty($upgradeFiles)) {
            $steps[] = $this->step('discover_files', 'skipped', 'No upgrade files found.');
            return $this->result(true, $moduleName, $steps, $snapshotDir, $errors);
        }

        foreach ($upgradeFiles as $file) {
            $steps[] = $this->step('discovered', 'info', $file);
        }

        if ($this->dryRun) {
            $steps[] = $this->step('dry_run', 'info', 'Dry-run mode: no changes will be written.');
            return $this->result(true, $moduleName, $steps, $snapshotDir, $errors);
        }

        // ── 4. Maintenance mode ON ────────────────────────────────────────────
        if ($this->maintenanceMode) {
            $this->callArtisan('down', ['--render' => 'errors::503']);
            $steps[] = $this->step('maintenance_mode', 'enabled', 'Application set to maintenance mode.');
        }

        try {
            // ── 5. Pre-upgrade backup ─────────────────────────────────────────
            $snapshotDir = $this->backup->run($moduleName, $modulePath);
            $steps[]     = $this->step('backup', 'created', $snapshotDir);

            // ── 6. Execute upgrade files ──────────────────────────────────────
            foreach ($upgradeFiles as $file) {
                [$stepStatus, $stepDetail] = $this->executeUpgradeFile($file, $moduleName);
                $steps[] = $this->step('execute_file', $stepStatus, basename($file) . ': ' . $stepDetail);

                if ($stepStatus === 'failed') {
                    $errors[] = "Upgrade file failed: " . basename($file) . ' — ' . $stepDetail;
                    break;
                }
            }

            // ── 7. Post-upgrade health check ──────────────────────────────────
            if (empty($errors)) {
                $healthResult = $this->runHealthCheck($moduleName);
                $steps[]      = $this->step('health_check', $healthResult['ok'] ? 'passed' : 'failed', $healthResult['detail']);

                if (! $healthResult['ok']) {
                    $errors[] = 'Post-upgrade health check failed: ' . $healthResult['detail'];
                }
            }

            // ── 8. Rollback on any error ──────────────────────────────────────
            if (! empty($errors) && $snapshotDir) {
                $rbResult = $this->rollback->rollback($snapshotDir);
                $steps[]  = $this->step(
                    'rollback',
                    $rbResult['ok'] ? 'completed' : 'partial',
                    'Restored ' . count($rbResult['restored_tables']) . ' tables and '
                        . $rbResult['restored_files'] . ' files.'
                        . (! empty($rbResult['errors']) ? ' Errors: ' . implode('; ', $rbResult['errors']) : '')
                );
            }

            // ── 9. Record upgrade history ─────────────────────────────────────
            if (empty($errors)) {
                $this->recordHistory($moduleName, $manifest['version'] ?? 'unknown', $upgradeFiles, $snapshotDir);
                $steps[] = $this->step('record_history', 'saved', 'Upgrade history recorded.');
            }
        } finally {
            // ── 10. Maintenance mode OFF ──────────────────────────────────────
            if ($this->maintenanceMode) {
                $this->callArtisan('up');
                $steps[] = $this->step('maintenance_mode', 'disabled', 'Application back online.');
            }
        }

        return $this->result(empty($errors), $moduleName, $steps, $snapshotDir, $errors);
    }

    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Execute a single upgrade file inside a DB transaction.
     *
     * @return array{0: string, 1: string}  [status, detail]
     */
    private function executeUpgradeFile(string $filePath, string $moduleName): array
    {
        $upgradeFile = basename($filePath);

        if ($this->upgradeTracking->hasSuccessfulRun($moduleName, $upgradeFile)) {
            return ['skipped', 'Already executed in a prior successful run.'];
        }

        try {
            DB::transaction(function () use ($filePath, $moduleName) {
                $result = require $filePath;

                // Support class-based upgrade files with a handle() or run() method
                if (is_object($result) && method_exists($result, 'handle')) {
                    $result->handle();
                } elseif (is_object($result) && method_exists($result, 'run')) {
                    $result->run();
                } elseif ($result instanceof \Illuminate\Database\Migrations\Migration) {
                    $result->up();
                }
            });

            $this->upgradeTracking->markSuccess($moduleName, $upgradeFile);

            return ['applied', 'OK'];
        } catch (\Throwable $e) {
            $this->upgradeTracking->markFailed($moduleName, $upgradeFile, $e->getMessage());

            return ['failed', $e->getMessage()];
        }
    }

    /**
     * Discover upgrade migration + script files for the given module.
     * Files are sorted by filename (date-prefixed migration style).
     *
     * @return string[]
     */
    private function discoverUpgradeFiles(string $modulePath): array
    {
        $files = [];

        foreach (['Upgrade/Migrations', 'Upgrade/Scripts'] as $rel) {
            $dir = $modulePath . DIRECTORY_SEPARATOR . $rel;
            if (is_dir($dir)) {
                $found = glob($dir . DIRECTORY_SEPARATOR . '*.php') ?: [];
                sort($found);
                $files = array_merge($files, $found);
            }
        }

        return $files;
    }

    /**
     * Run the module's dedicated health command if it exists, or fall back
     * to `modules:health {module}`.
     *
     * @return array{ok: bool, detail: string}
     */
    private function runHealthCheck(string $moduleName): array
    {
        // Try module-specific health command first
        $moduleAlias = strtolower($moduleName);
        $specific    = $moduleAlias . ':health';

        try {
            $exitCode = Artisan::call($specific, ['--json' => true]);
            return ['ok' => $exitCode === 0, 'detail' => Artisan::output()];
        } catch (\Throwable) {
            // Fall back to global health command
        }

        try {
            $exitCode = Artisan::call('modules:health', ['module' => $moduleName]);
            return ['ok' => $exitCode === 0, 'detail' => Artisan::output()];
        } catch (\Throwable $e) {
            // No health command available — treat as OK to avoid blocking
            return ['ok' => true, 'detail' => 'No health command found; skipped.'];
        }
    }

    /**
     * Persist an upgrade_history record when the `upgrade_history` table exists.
     *
     * @param  string[]  $files
     */
    private function recordHistory(string $moduleName, string $version, array $files, ?string $snapshotDir): void
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('upgrade_history')) {
            return;
        }

        DB::table('upgrade_history')->insert([
            'module_name'   => $moduleName,
            'version'       => $version,
            'files_applied' => json_encode(array_map('basename', $files)),
            'snapshot_path' => $snapshotDir,
            'status'        => 'success',
            'applied_at'    => now(),
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function loadManifest(string $modulePath): array
    {
        $path = $modulePath . '/module.json';

        if (! file_exists($path)) {
            return [];
        }

        $decoded = json_decode(file_get_contents($path), true);

        return is_array($decoded) ? $decoded : [];
    }

    private function callArtisan(string $command, array $params = []): void
    {
        try {
            Artisan::call($command, $params);
        } catch (\Throwable) {
            // Best-effort; do not abort the upgrade on artisan failures
        }
    }

    /**
     * @param  array<int, array{step: string, status: string, detail: string}>  $steps
     * @return array{ok: bool, dry_run: bool, module: string, steps: array, snapshot_dir: string|null, errors: string[]}
     */
    private function result(bool $ok, string $module, array $steps, ?string $snapshotDir, array $errors): array
    {
        return [
            'ok'           => $ok,
            'dry_run'      => $this->dryRun,
            'module'       => $module,
            'steps'        => $steps,
            'snapshot_dir' => $snapshotDir,
            'errors'       => $errors,
        ];
    }

    /**
     * @return array{step: string, status: string, detail: string}
     */
    private function step(string $step, string $status, string $detail): array
    {
        return compact('step', 'status', 'detail');
    }
}
