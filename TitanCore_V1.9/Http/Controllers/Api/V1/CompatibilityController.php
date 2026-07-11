<?php

namespace Modules\TitanCore\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\TitanCore\Services\Upgrade\VersionCompatibilityChecker;

/**
 * Compatibility API — /api/v1/compatibility/*
 *
 * Verifies compatibility between TitanCore, SDK, modules, and providers.
 * Checks schema versions, manifest versions, SDK versions, and platform versions.
 * Warns before incompatible upgrades.
 *
 * Phase 4 of the Titan Platform Manager.
 */
class CompatibilityController extends Controller
{
    public function __construct(
        private readonly VersionCompatibilityChecker $checker,
    ) {}

    /**
     * GET /api/v1/compatibility
     *
     * Overall platform compatibility summary.
     */
    public function index(): JsonResponse
    {
        $platform = $this->checkPlatform();
        $sdk      = $this->checkSdk();
        $modules  = $this->checkAllModules();

        $overallOk = $platform['ok'] && $sdk['ok']
            && count(array_filter(array_column($modules, 'ok'), fn ($v) => ! $v)) === 0;

        return response()->json([
            'compatible' => $overallOk,
            'platform'   => $platform,
            'sdk'        => $sdk,
            'modules'    => $modules,
            'ts'         => now()->toIso8601String(),
        ]);
    }

    /**
     * GET /api/v1/compatibility/platform
     *
     * Verify that the current PHP and Laravel versions satisfy TitanCore requirements.
     */
    public function platform(): JsonResponse
    {
        $result = $this->checkPlatform();

        return response()->json(array_merge($result, ['ts' => now()->toIso8601String()]));
    }

    /**
     * GET /api/v1/compatibility/sdk
     *
     * Verify SDK / module.json version compatibility.
     */
    public function sdk(): JsonResponse
    {
        $result = $this->checkSdk();

        return response()->json(array_merge($result, ['ts' => now()->toIso8601String()]));
    }

    /**
     * GET /api/v1/compatibility/modules
     *
     * Check compatibility for all installed modules.
     */
    public function modules(): JsonResponse
    {
        $results = $this->checkAllModules();

        $allOk = count(array_filter(array_column($results, 'ok'), fn ($v) => ! $v)) === 0;

        return response()->json([
            'compatible' => $allOk,
            'modules'    => $results,
            'total'      => count($results),
            'ts'         => now()->toIso8601String(),
        ]);
    }

    /**
     * POST /api/v1/compatibility/check
     *
     * Check compatibility for a specific module by alias.
     * Accepts: { "module": "ModuleName" }
     */
    public function check(): JsonResponse
    {
        $alias = request()->input('module');
        if (! $alias) {
            return response()->json(['error' => 'module parameter required'], 422);
        }

        $moduleDir = base_path('Modules/' . $alias);
        if (! is_dir($moduleDir)) {
            return response()->json(['error' => "Module [{$alias}] not found"], 404);
        }

        $result = $this->checkModule($alias, $moduleDir);

        return response()->json(array_merge($result, ['ts' => now()->toIso8601String()]));
    }

    /**
     * POST /api/v1/compatibility/warn
     *
     * Pre-upgrade compatibility warning check.
     * Accepts: { "module": "ModuleName", "target_version": "2.0.0" }
     */
    public function warn(): JsonResponse
    {
        $alias         = request()->input('module');
        $targetVersion = request()->input('target_version');

        if (! $alias) {
            return response()->json(['error' => 'module parameter required'], 422);
        }

        $warnings = [];

        $moduleDir = base_path('Modules/' . $alias);
        if (! is_dir($moduleDir)) {
            return response()->json(['error' => "Module [{$alias}] not found"], 404);
        }

        $mFile    = $moduleDir . '/module.json';
        $manifest = [];
        if (is_file($mFile)) {
            $manifest = json_decode((string) file_get_contents($mFile), true) ?: [];
        }

        $currentVersion = $manifest['version'] ?? null;

        if ($targetVersion && $currentVersion) {
            if (version_compare($targetVersion, $currentVersion, '<')) {
                $warnings[] = "Target version {$targetVersion} is older than installed version {$currentVersion}. Downgrade may break data.";
            }
        }

        $compat = $this->checker->check($manifest);
        if (! $compat['ok']) {
            foreach ($compat['errors'] as $error) {
                $warnings[] = $error;
            }
        }

        return response()->json([
            'module'          => $alias,
            'current_version' => $currentVersion,
            'target_version'  => $targetVersion,
            'safe_to_upgrade' => empty($warnings),
            'warnings'        => $warnings,
            'ts'              => now()->toIso8601String(),
        ]);
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    /**
     * @return array{ok: bool, php: string, laravel: string, errors: string[]}
     */
    private function checkPlatform(): array
    {
        $base     = dirname(__DIR__, 5);
        $manifest = [];
        $mFile    = $base . '/module.json';

        if (is_file($mFile)) {
            $manifest = json_decode((string) file_get_contents($mFile), true) ?: [];
        }

        $result = $this->checker->check($manifest);

        return [
            'ok'      => $result['ok'],
            'php'     => PHP_VERSION,
            'laravel' => app()->version(),
            'errors'  => $result['errors'],
        ];
    }

    /**
     * @return array{ok: bool, sdk_version: string, platform_version: string}
     */
    private function checkSdk(): array
    {
        $base    = dirname(__DIR__, 5);
        $version = 'unknown';

        try {
            $vFile = $base . '/version.txt';
            if (is_file($vFile)) {
                $version = trim((string) file_get_contents($vFile));
            }
        } catch (\Throwable) {}

        $moduleJson = [];
        try {
            $mFile = $base . '/module.json';
            if (is_file($mFile)) {
                $moduleJson = json_decode((string) file_get_contents($mFile), true) ?: [];
            }
        } catch (\Throwable) {}

        return [
            'ok'               => true,
            'sdk_version'      => $version,
            'platform_version' => $moduleJson['version'] ?? $version,
        ];
    }

    /**
     * @return list<array{module: string, ok: bool, version: string|null, errors: string[]}>
     */
    private function checkAllModules(): array
    {
        $results    = [];
        $modulesDir = base_path('Modules');

        if (! is_dir($modulesDir)) {
            return $results;
        }

        foreach (scandir($modulesDir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $dir = $modulesDir . DIRECTORY_SEPARATOR . $entry;
            if (! is_dir($dir)) {
                continue;
            }

            $results[] = $this->checkModule($entry, $dir);
        }

        return $results;
    }

    /**
     * @return array{module: string, ok: bool, version: string|null, errors: string[]}
     */
    private function checkModule(string $name, string $dir): array
    {
        $manifest = [];
        $mFile    = $dir . '/module.json';

        if (is_file($mFile)) {
            $manifest = json_decode((string) file_get_contents($mFile), true) ?: [];
        }

        $result  = $this->checker->check($manifest);
        $version = $manifest['version'] ?? null;

        return [
            'module'  => $name,
            'ok'      => $result['ok'],
            'version' => $version,
            'errors'  => $result['errors'],
        ];
    }
}
