<?php

namespace Modules\TitanCore\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

/**
 * Module & Registry API — /api/v1/modules/*
 *
 * Exposes module registry metadata through platform contracts.
 * Does not expose internal implementation details.
 */
class ModulesController extends Controller
{
    private function loadModuleRegistry(): array
    {
        $modules = [];

        // Read nWidart modules_statuses.json if available (standard module location)
        $statusFile = base_path('modules_statuses.json');
        $statuses   = [];
        if (is_file($statusFile)) {
            try {
                $statuses = json_decode((string) file_get_contents($statusFile), true) ?: [];
            } catch (\Throwable) {}
        }

        // Scan Modules/ directory if it exists
        $modulesDir = base_path('Modules');
        if (is_dir($modulesDir)) {
            foreach (scandir($modulesDir) ?: [] as $entry) {
                if ($entry === '.' || $entry === '..') {
                    continue;
                }
                $moduleDir = $modulesDir . DIRECTORY_SEPARATOR . $entry;
                if (! is_dir($moduleDir)) {
                    continue;
                }

                $meta = $this->readModuleMeta($moduleDir, $entry);
                $meta['enabled'] = (bool) ($statuses[$entry] ?? $statuses[strtolower($entry)] ?? true);
                $modules[]       = $meta;
            }
        }

        // Always include TitanCore itself from the module.json at the known path
        $titanBase = dirname(__DIR__, 5);
        $titanMeta = $this->readModuleMeta($titanBase, 'TitanCore');
        $found     = false;
        foreach ($modules as $m) {
            if ($m['alias'] === ($titanMeta['alias'] ?? 'titancore')) {
                $found = true;
                break;
            }
        }
        if (! $found) {
            $titanMeta['enabled'] = true;
            $modules[]            = $titanMeta;
        }

        return $modules;
    }

    private function readModuleMeta(string $dir, string $fallbackName): array
    {
        $name    = $fallbackName;
        $alias   = strtolower($fallbackName);
        $version = null;
        $desc    = null;
        $caps    = [];

        try {
            $mFile = $dir . DIRECTORY_SEPARATOR . 'module.json';
            if (is_file($mFile)) {
                $data    = json_decode((string) file_get_contents($mFile), true) ?: [];
                $name    = $data['name'] ?? $name;
                $alias   = $data['alias'] ?? $alias;
                $desc    = $data['description'] ?? null;
                $caps    = $data['capabilities'] ?? [];
                $version = $data['version'] ?? null;
            }
        } catch (\Throwable) {}

        if ($version === null) {
            try {
                $vFile = $dir . DIRECTORY_SEPARATOR . 'version.txt';
                if (is_file($vFile)) {
                    $version = trim((string) file_get_contents($vFile));
                }
            } catch (\Throwable) {}
        }

        return [
            'id'           => $alias,
            'name'         => $name,
            'alias'        => $alias,
            'version'      => $version,
            'description'  => $desc,
            'capabilities' => $caps,
        ];
    }

    /**
     * GET /api/v1/modules
     *
     * List all registered modules.
     */
    public function index(): JsonResponse
    {
        $modules = $this->loadModuleRegistry();

        return response()->json([
            'data'  => $modules,
            'total' => count($modules),
            'ts'    => now()->toIso8601String(),
        ]);
    }

    /**
     * GET /api/v1/modules/{id}
     *
     * Get details for a specific module by alias/id.
     */
    public function show(string $id): JsonResponse
    {
        $modules = $this->loadModuleRegistry();

        foreach ($modules as $module) {
            if ($module['id'] === $id || $module['alias'] === $id) {
                return response()->json(['data' => $module]);
            }
        }

        return response()->json(['error' => 'Module not found'], 404);
    }

    /**
     * GET /api/v1/modules/discover
     *
     * Trigger discovery of modules from the filesystem and return metadata.
     */
    public function discover(): JsonResponse
    {
        $modules = $this->loadModuleRegistry();

        return response()->json([
            'data'      => $modules,
            'total'     => count($modules),
            'source'    => 'filesystem',
            'ts'        => now()->toIso8601String(),
        ]);
    }

    /**
     * POST /api/v1/modules/refresh
     *
     * Signal a registry refresh (clears cached module data).
     */
    public function refresh(): JsonResponse
    {
        try {
            \Illuminate\Support\Facades\Artisan::call('cache:clear');
        } catch (\Throwable) {}

        return response()->json([
            'status'  => 'ok',
            'message' => 'Module registry refresh triggered',
            'ts'      => now()->toIso8601String(),
        ]);
    }

    /**
     * POST /api/v1/modules/install
     *
     * Not supported — module installation is handled outside the runtime API.
     */
    public function install(): JsonResponse
    {
        return response()->json([
            'error'   => 'Module installation must be performed via the platform CLI or admin console.',
            'status'  => 501,
        ], 501);
    }

    /**
     * POST /api/v1/modules/enable
     *
     * Enable a module by alias. Delegates to Artisan module:enable if available.
     */
    public function enable(): JsonResponse
    {
        $alias = request()->input('module');
        if (! $alias) {
            return response()->json(['error' => 'module parameter required'], 422);
        }

        try {
            \Illuminate\Support\Facades\Artisan::call('module:enable', ['module' => $alias]);

            return response()->json([
                'status'  => 'ok',
                'message' => "Module [{$alias}] enabled",
                'ts'      => now()->toIso8601String(),
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /api/v1/modules/disable
     *
     * Disable a module by alias.
     */
    public function disable(): JsonResponse
    {
        $alias = request()->input('module');
        if (! $alias) {
            return response()->json(['error' => 'module parameter required'], 422);
        }

        try {
            \Illuminate\Support\Facades\Artisan::call('module:disable', ['module' => $alias]);

            return response()->json([
                'status'  => 'ok',
                'message' => "Module [{$alias}] disabled",
                'ts'      => now()->toIso8601String(),
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * DELETE /api/v1/modules/remove
     *
     * Not supported at runtime — module removal must be handled via CLI.
     */
    public function remove(): JsonResponse
    {
        return response()->json([
            'error'  => 'Module removal must be performed via the platform CLI.',
            'status' => 501,
        ], 501);
    }
}
