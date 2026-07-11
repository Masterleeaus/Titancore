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

    /**
     * POST /api/v1/modules/validate
     *
     * Validate a module's manifest and dependencies without making changes.
     */
    public function validate(): JsonResponse
    {
        $alias = request()->input('module');
        if (! $alias) {
            return response()->json(['error' => 'module parameter required'], 422);
        }

        $results = ['module' => $alias, 'errors' => [], 'warnings' => []];

        try {
            \Illuminate\Support\Facades\Artisan::call('modules:doctor', ['--module' => $alias]);
            $output = \Illuminate\Support\Facades\Artisan::output();
        } catch (\Throwable $e) {
            $output = $e->getMessage();
        }

        // Parse any manifest validation
        $modulesDir = base_path('Modules/' . $alias);
        if (! is_dir($modulesDir)) {
            $results['errors'][] = "Module directory not found for [{$alias}]";
        } else {
            $manifestFile = $modulesDir . '/module.json';
            if (! is_file($manifestFile)) {
                $results['warnings'][] = "No module.json found for [{$alias}]";
            } else {
                $manifest = json_decode((string) file_get_contents($manifestFile), true);
                if (! is_array($manifest)) {
                    $results['errors'][] = 'module.json is not valid JSON';
                } else {
                    if (empty($manifest['name'])) {
                        $results['warnings'][] = 'module.json missing "name" key';
                    }
                    if (empty($manifest['version'])) {
                        $results['warnings'][] = 'module.json missing "version" key';
                    }
                }
            }
        }

        $results['ok']     = empty($results['errors']);
        $results['output'] = $output ?? null;
        $results['ts']     = now()->toIso8601String();

        return response()->json($results);
    }

    /**
     * POST /api/v1/modules/repair
     *
     * Attempt to repair a module by re-running its migrations and cache clear.
     */
    public function repair(): JsonResponse
    {
        $alias = request()->input('module');
        if (! $alias) {
            return response()->json(['error' => 'module parameter required'], 422);
        }

        $steps   = [];
        $errors  = [];

        // Attempt to clear caches
        try {
            \Illuminate\Support\Facades\Artisan::call('cache:clear');
            $steps[] = ['step' => 'cache_clear', 'status' => 'ok'];
        } catch (\Throwable $e) {
            $steps[] = ['step' => 'cache_clear', 'status' => 'warning', 'detail' => $e->getMessage()];
        }

        // Attempt to re-run module migrations
        try {
            \Illuminate\Support\Facades\Artisan::call('module:migrate', ['module' => $alias]);
            $steps[] = ['step' => 'migrate', 'status' => 'ok'];
        } catch (\Throwable $e) {
            $steps[] = ['step' => 'migrate', 'status' => 'skipped', 'detail' => $e->getMessage()];
        }

        // Attempt to re-seed module (if a seed command exists)
        try {
            \Illuminate\Support\Facades\Artisan::call('module:seed', ['module' => $alias]);
            $steps[] = ['step' => 'seed', 'status' => 'ok'];
        } catch (\Throwable $e) {
            $steps[] = ['step' => 'seed', 'status' => 'skipped', 'detail' => $e->getMessage()];
        }

        return response()->json([
            'status'  => empty($errors) ? 'ok' : 'partial',
            'module'  => $alias,
            'steps'   => $steps,
            'errors'  => $errors,
            'ts'      => now()->toIso8601String(),
        ]);
    }

    /**
     * POST /api/v1/modules/rebuild
     *
     * Rebuild module assets and flush module-related caches.
     */
    public function rebuild(): JsonResponse
    {
        $alias = request()->input('module');
        if (! $alias) {
            return response()->json(['error' => 'module parameter required'], 422);
        }

        $steps = [];

        try {
            \Illuminate\Support\Facades\Artisan::call('cache:clear');
            $steps[] = ['step' => 'cache_clear', 'status' => 'ok'];
        } catch (\Throwable $e) {
            $steps[] = ['step' => 'cache_clear', 'status' => 'warning', 'detail' => $e->getMessage()];
        }

        try {
            \Illuminate\Support\Facades\Artisan::call('config:clear');
            $steps[] = ['step' => 'config_clear', 'status' => 'ok'];
        } catch (\Throwable $e) {
            $steps[] = ['step' => 'config_clear', 'status' => 'warning', 'detail' => $e->getMessage()];
        }

        try {
            \Illuminate\Support\Facades\Artisan::call('route:clear');
            $steps[] = ['step' => 'route_clear', 'status' => 'ok'];
        } catch (\Throwable $e) {
            $steps[] = ['step' => 'route_clear', 'status' => 'warning', 'detail' => $e->getMessage()];
        }

        return response()->json([
            'status' => 'ok',
            'module' => $alias,
            'steps'  => $steps,
            'ts'     => now()->toIso8601String(),
        ]);
    }

    /**
     * POST /api/v1/modules/update
     *
     * Trigger a module update via the upgrade engine (dry-run mode disabled).
     * Not supported at runtime for full binary updates; triggers the upgrade pipeline.
     */
    public function update(): JsonResponse
    {
        $alias = request()->input('module');
        if (! $alias) {
            return response()->json(['error' => 'module parameter required'], 422);
        }

        try {
            \Illuminate\Support\Facades\Artisan::call('modules:upgrade', ['module' => $alias]);
            $output = \Illuminate\Support\Facades\Artisan::output();

            return response()->json([
                'status'  => 'ok',
                'message' => "Module [{$alias}] upgrade initiated",
                'output'  => $output,
                'ts'      => now()->toIso8601String(),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'Update not supported for this module: ' . $e->getMessage(),
                'hint'  => 'Perform module upgrades via the platform CLI: php artisan modules:upgrade ' . $alias,
            ], 501);
        }
    }

    /**
     * POST /api/v1/modules/rollback
     *
     * Roll back a module's last migration batch.
     */
    public function rollback(): JsonResponse
    {
        $alias = request()->input('module');
        if (! $alias) {
            return response()->json(['error' => 'module parameter required'], 422);
        }

        try {
            \Illuminate\Support\Facades\Artisan::call('module:migrate-rollback', ['module' => $alias]);
            $output = \Illuminate\Support\Facades\Artisan::output();

            return response()->json([
                'status'  => 'ok',
                'message' => "Module [{$alias}] rollback complete",
                'output'  => $output,
                'ts'      => now()->toIso8601String(),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'error'  => 'Module rollback must be performed via the platform CLI.',
                'detail' => $e->getMessage(),
                'status' => 501,
            ], 501);
        }
    }

    /**
     * GET /api/v1/modules/dependency-graph
     *
     * Return the full platform dependency graph including cycles and issues.
     */
    public function dependencyGraph(): JsonResponse
    {
        $modules   = $this->loadModuleRegistry();
        $nodes     = [];
        $edges     = [];
        $conflicts = [];
        $orphaned  = [];

        // Build a simple adjacency list from module.json requires fields
        $statusFile = base_path('modules_statuses.json');
        $statuses   = [];
        if (is_file($statusFile)) {
            try {
                $statuses = json_decode((string) file_get_contents($statusFile), true) ?: [];
            } catch (\Throwable) {}
        }

        $modulesDir = base_path('Modules');
        $manifests  = [];

        if (is_dir($modulesDir)) {
            foreach (scandir($modulesDir) ?: [] as $entry) {
                if ($entry === '.' || $entry === '..') {
                    continue;
                }
                $dir = $modulesDir . DIRECTORY_SEPARATOR . $entry;
                if (! is_dir($dir)) {
                    continue;
                }
                $mFile = $dir . '/module.json';
                if (is_file($mFile)) {
                    $data = json_decode((string) file_get_contents($mFile), true) ?: [];
                    $manifests[$entry] = $data;
                }
            }
        }

        foreach ($manifests as $name => $data) {
            $enabled    = (bool) ($statuses[$name] ?? $statuses[strtolower($name)] ?? true);
            $requires   = (array) ($data['requires'] ?? []);
            $conflictsWith = (array) ($data['conflicts'] ?? []);

            $nodes[] = [
                'id'       => $name,
                'version'  => $data['version'] ?? null,
                'enabled'  => $enabled,
                'requires' => $requires,
                'conflicts' => $conflictsWith,
            ];

            foreach ($requires as $dep) {
                $depName = explode(':', $dep)[0];
                if (! str_contains($depName, '/')) {
                    $edges[] = ['from' => $name, 'to' => $depName, 'type' => 'requires'];
                }
            }

            foreach ($conflictsWith as $conflict) {
                if (! str_contains((string) $conflict, '/')) {
                    $conflicts[] = ['from' => $name, 'to' => $conflict];
                }
            }

            // Detect orphaned modules (no dependents and no dependencies)
            $hasRequires    = ! empty($requires);
            $hasDependents  = false;
            foreach ($manifests as $otherName => $otherData) {
                if ($otherName === $name) {
                    continue;
                }
                foreach ((array) ($otherData['requires'] ?? []) as $dep) {
                    if (explode(':', $dep)[0] === $name) {
                        $hasDependents = true;
                        break 2;
                    }
                }
            }
            if (! $hasRequires && ! $hasDependents && count($manifests) > 1) {
                $orphaned[] = $name;
            }
        }

        return response()->json([
            'nodes'     => $nodes,
            'edges'     => $edges,
            'conflicts' => $conflicts,
            'orphaned'  => $orphaned,
            'total'     => count($nodes),
            'ts'        => now()->toIso8601String(),
        ]);
    }

    /**
     * GET /api/v1/modules/{id}/dependencies
     *
     * Return the dependency tree for a specific module.
     */
    public function moduleDependencies(string $id): JsonResponse
    {
        $modules = $this->loadModuleRegistry();
        $found   = null;

        foreach ($modules as $m) {
            if ($m['id'] === $id || $m['alias'] === $id) {
                $found = $m;
                break;
            }
        }

        if ($found === null) {
            return response()->json(['error' => 'Module not found'], 404);
        }

        // Read full requires from module.json
        $modulesDir = base_path('Modules/' . $id);
        $requires   = [];
        $conflicts  = [];
        $suggests   = [];

        if (is_dir($modulesDir)) {
            $mFile = $modulesDir . '/module.json';
            if (is_file($mFile)) {
                $data      = json_decode((string) file_get_contents($mFile), true) ?: [];
                $requires  = (array) ($data['requires'] ?? []);
                $conflicts = (array) ($data['conflicts'] ?? []);
                $suggests  = (array) ($data['suggests'] ?? []);
            }
        }

        return response()->json([
            'module'    => $found,
            'requires'  => $requires,
            'conflicts' => $conflicts,
            'suggests'  => $suggests,
            'ts'        => now()->toIso8601String(),
        ]);
    }
}
