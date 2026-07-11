<?php

namespace Modules\TitanCore\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\TitanCore\Services\Upgrade\UpgradeEngine;
use Modules\TitanCore\Services\Upgrade\VersionCompatibilityChecker;

/**
 * Upgrade Center API — /api/v1/upgrades/*
 *
 * Manages platform upgrades, SDK upgrades, module upgrades,
 * migrations, rollbacks, upgrade validation, and post-upgrade verification.
 *
 * Phase 14 of the Titan Platform Manager.
 */
class UpgradesController extends Controller
{
    public function __construct(
        private readonly UpgradeEngine              $engine,
        private readonly VersionCompatibilityChecker $checker,
    ) {}

    /**
     * GET /api/v1/upgrades
     *
     * List all modules with upgrade status information.
     */
    public function index(): JsonResponse
    {
        $modules    = [];
        $modulesDir = base_path('Modules');

        if (is_dir($modulesDir)) {
            foreach (scandir($modulesDir) ?: [] as $entry) {
                if ($entry === '.' || $entry === '..') {
                    continue;
                }
                $dir = $modulesDir . DIRECTORY_SEPARATOR . $entry;
                if (! is_dir($dir)) {
                    continue;
                }

                $mFile    = $dir . '/module.json';
                $manifest = [];
                if (is_file($mFile)) {
                    $manifest = json_decode((string) file_get_contents($mFile), true) ?: [];
                }

                $compat    = $this->checker->check($manifest);
                $lastEntry = $this->lastUpgradeRecord($entry);

                $modules[] = [
                    'module'          => $entry,
                    'version'         => $manifest['version'] ?? null,
                    'compatible'      => $compat['ok'],
                    'compat_errors'   => $compat['errors'],
                    'last_upgraded'   => $lastEntry['applied_at'] ?? null,
                    'upgrade_version' => $lastEntry['version'] ?? null,
                    'upgrade_status'  => $lastEntry['status'] ?? 'never',
                ];
            }
        }

        return response()->json([
            'data'  => $modules,
            'total' => count($modules),
            'ts'    => now()->toIso8601String(),
        ]);
    }

    /**
     * GET /api/v1/upgrades/check
     *
     * Check whether any modules have available upgrades or compatibility issues.
     */
    public function check(): JsonResponse
    {
        $results    = [];
        $modulesDir = base_path('Modules');

        if (is_dir($modulesDir)) {
            foreach (scandir($modulesDir) ?: [] as $entry) {
                if ($entry === '.' || $entry === '..') {
                    continue;
                }
                $dir = $modulesDir . DIRECTORY_SEPARATOR . $entry;
                if (! is_dir($dir)) {
                    continue;
                }

                $mFile    = $dir . '/module.json';
                $manifest = [];
                if (is_file($mFile)) {
                    $manifest = json_decode((string) file_get_contents($mFile), true) ?: [];
                }

                $compat = $this->checker->check($manifest);

                if (! $compat['ok']) {
                    $results[] = [
                        'module'  => $entry,
                        'version' => $manifest['version'] ?? null,
                        'issues'  => $compat['errors'],
                    ];
                }
            }
        }

        return response()->json([
            'incompatible' => count($results),
            'modules'      => $results,
            'ts'           => now()->toIso8601String(),
        ]);
    }

    /**
     * POST /api/v1/upgrades/run
     *
     * Run the upgrade pipeline for a specific module.
     * Accepts: { "module": "ModuleName", "dry_run": false }
     */
    public function run(): JsonResponse
    {
        $alias  = request()->input('module');
        $dryRun = (bool) request()->input('dry_run', false);

        if (! $alias) {
            return response()->json(['error' => 'module parameter required'], 422);
        }

        $modulePath = base_path('Modules/' . $alias);
        if (! is_dir($modulePath)) {
            return response()->json(['error' => "Module [{$alias}] not found"], 404);
        }

        $this->engine->setDryRun($dryRun);
        $this->engine->setMaintenanceMode(false); // Disable maintenance mode for API-triggered upgrades

        $result = $this->engine->run($alias);

        $status = $result['ok'] ? 200 : 500;

        return response()->json(array_merge($result, ['ts' => now()->toIso8601String()]), $status);
    }

    /**
     * POST /api/v1/upgrades/validate
     *
     * Validate a module's upgrade path without executing.
     * Dry-run mode: checks version compatibility and dependency constraints.
     */
    public function validate(): JsonResponse
    {
        $alias = request()->input('module');
        if (! $alias) {
            return response()->json(['error' => 'module parameter required'], 422);
        }

        $modulePath = base_path('Modules/' . $alias);
        if (! is_dir($modulePath)) {
            return response()->json(['error' => "Module [{$alias}] not found"], 404);
        }

        $this->engine->setDryRun(true);
        $this->engine->setMaintenanceMode(false);

        $result = $this->engine->run($alias);

        return response()->json(array_merge($result, ['ts' => now()->toIso8601String()]));
    }

    /**
     * POST /api/v1/upgrades/rollback
     *
     * Roll back the last migration batch for a specific module.
     * Accepts: { "module": "ModuleName" }
     */
    public function rollback(): JsonResponse
    {
        $alias = request()->input('module');
        if (! $alias) {
            return response()->json(['error' => 'module parameter required'], 422);
        }

        $steps  = [];
        $errors = [];

        try {
            \Illuminate\Support\Facades\Artisan::call('module:migrate-rollback', ['module' => $alias]);
            $output  = \Illuminate\Support\Facades\Artisan::output();
            $steps[] = ['step' => 'migrate_rollback', 'status' => 'ok', 'detail' => $output];
        } catch (\Throwable $e) {
            $errors[] = $e->getMessage();
            $steps[]  = ['step' => 'migrate_rollback', 'status' => 'failed', 'detail' => $e->getMessage()];
        }

        return response()->json([
            'ok'     => empty($errors),
            'module' => $alias,
            'steps'  => $steps,
            'errors' => $errors,
            'ts'     => now()->toIso8601String(),
        ], empty($errors) ? 200 : 500);
    }

    /**
     * GET /api/v1/upgrades/history
     *
     * Return upgrade history for all modules (from upgrade_history table if available).
     */
    public function history(): JsonResponse
    {
        $records = [];

        try {
            if (Schema::hasTable('upgrade_history')) {
                $rows = DB::table('upgrade_history')
                    ->orderByDesc('applied_at')
                    ->limit(100)
                    ->get(['module_name', 'version', 'status', 'applied_at', 'files_applied']);

                foreach ($rows as $row) {
                    $records[] = [
                        'module'        => $row->module_name,
                        'version'       => $row->version,
                        'status'        => $row->status,
                        'applied_at'    => $row->applied_at,
                        'files_applied' => json_decode($row->files_applied ?? '[]', true) ?: [],
                    ];
                }
            }
        } catch (\Throwable) {}

        return response()->json([
            'data'  => $records,
            'total' => count($records),
            'ts'    => now()->toIso8601String(),
        ]);
    }

    /**
     * POST /api/v1/upgrades/verify
     *
     * Run a post-upgrade verification check for a specific module.
     */
    public function verify(): JsonResponse
    {
        $alias = request()->input('module');
        if (! $alias) {
            return response()->json(['error' => 'module parameter required'], 422);
        }

        $checks = [];
        $errors = [];

        // Check module is still properly registered and enabled
        $statusFile = base_path('modules_statuses.json');
        $statuses   = [];
        if (is_file($statusFile)) {
            try {
                $statuses = json_decode((string) file_get_contents($statusFile), true) ?: [];
            } catch (\Throwable) {}
        }

        $enabled = (bool) ($statuses[$alias] ?? $statuses[strtolower($alias)] ?? true);
        $checks[] = [
            'check'   => 'module_status',
            'ok'      => $enabled,
            'message' => $enabled ? 'Module is enabled' : 'Module is disabled',
        ];

        // Verify module.json is valid
        $mFile = base_path('Modules/' . $alias . '/module.json');
        if (is_file($mFile)) {
            $manifest = json_decode((string) file_get_contents($mFile), true);
            $checks[] = [
                'check'   => 'manifest_valid',
                'ok'      => is_array($manifest),
                'message' => is_array($manifest) ? 'module.json is valid JSON' : 'module.json is invalid',
            ];

            if (is_array($manifest)) {
                $compat    = $this->checker->check($manifest);
                $checks[] = [
                    'check'   => 'compatibility',
                    'ok'      => $compat['ok'],
                    'message' => $compat['ok'] ? 'Compatibility check passed' : implode('; ', $compat['errors']),
                ];
                if (! $compat['ok']) {
                    $errors = array_merge($errors, $compat['errors']);
                }
            }
        } else {
            $checks[] = [
                'check'   => 'manifest_valid',
                'ok'      => false,
                'message' => 'module.json not found',
            ];
        }

        $ok = count(array_filter(array_column($checks, 'ok'), fn ($v) => ! $v)) === 0;

        return response()->json([
            'ok'     => $ok,
            'module' => $alias,
            'checks' => $checks,
            'errors' => $errors,
            'ts'     => now()->toIso8601String(),
        ]);
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    private function lastUpgradeRecord(string $moduleName): array
    {
        if (! Schema::hasTable('upgrade_history')) {
            return [];
        }

        try {
            $row = DB::table('upgrade_history')
                ->where('module_name', $moduleName)
                ->orderByDesc('applied_at')
                ->first(['version', 'status', 'applied_at']);

            return $row ? (array) $row : [];
        } catch (\Throwable) {
            return [];
        }
    }
}
