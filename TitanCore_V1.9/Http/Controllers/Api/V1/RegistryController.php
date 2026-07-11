<?php

namespace Modules\TitanCore\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Artisan;
use Modules\TitanCore\Services\AssetDiscoveryService;

/**
 * Registry API — /api/v1/registry/*
 *
 * Provides UI and API for all platform registries:
 *   Module Registry, Provider Registry, Tool Registry,
 *   Workflow Registry, Agent Registry, Prompt Registry.
 *
 * Supports rebuild, refresh, validation and export per registry.
 *
 * Phase 5 of the Titan Platform Manager.
 */
class RegistryController extends Controller
{
    public function __construct(
        private readonly AssetDiscoveryService $discovery,
    ) {}

    /**
     * GET /api/v1/registry
     *
     * Summary of all registries and their item counts.
     */
    public function index(): JsonResponse
    {
        $base    = dirname(__DIR__, 5);
        $aiDir   = $base . '/AI';

        $summary = [
            'modules'   => $this->countModules(),
            'providers' => count($this->discovery->discoverProviders($aiDir)),
            'tools'     => count($this->discovery->discoverTools($aiDir)),
            'workflows' => count($this->discovery->discoverWorkflows($aiDir)),
            'agents'    => count($this->discovery->discoverAgents($aiDir)),
            'prompts'   => count($this->discovery->discoverPrompts($aiDir)),
        ];

        return response()->json([
            'registries' => $summary,
            'ts'         => now()->toIso8601String(),
        ]);
    }

    /**
     * GET /api/v1/registry/modules
     *
     * List all entries in the Module Registry.
     */
    public function modules(): JsonResponse
    {
        $modules    = [];
        $modulesDir = base_path('Modules');
        $statusFile = base_path('modules_statuses.json');
        $statuses   = [];

        if (is_file($statusFile)) {
            try {
                $statuses = json_decode((string) file_get_contents($statusFile), true) ?: [];
            } catch (\Throwable) {}
        }

        if (is_dir($modulesDir)) {
            foreach (scandir($modulesDir) ?: [] as $entry) {
                if ($entry === '.' || $entry === '..') {
                    continue;
                }
                $dir = $modulesDir . DIRECTORY_SEPARATOR . $entry;
                if (! is_dir($dir)) {
                    continue;
                }

                $meta    = $this->readModuleMeta($dir, $entry);
                $enabled = (bool) ($statuses[$entry] ?? $statuses[strtolower($entry)] ?? true);

                $modules[] = array_merge($meta, ['enabled' => $enabled]);
            }
        }

        return response()->json([
            'data'  => $modules,
            'total' => count($modules),
            'ts'    => now()->toIso8601String(),
        ]);
    }

    /**
     * GET /api/v1/registry/providers
     *
     * List all entries in the Provider Registry.
     */
    public function providers(): JsonResponse
    {
        $base      = dirname(__DIR__, 5);
        $providers = $this->discovery->discoverProviders($base . '/AI');

        return response()->json([
            'data'  => $providers,
            'total' => count($providers),
            'ts'    => now()->toIso8601String(),
        ]);
    }

    /**
     * GET /api/v1/registry/tools
     *
     * List all entries in the Tool Registry.
     */
    public function tools(): JsonResponse
    {
        $base  = dirname(__DIR__, 5);
        $tools = $this->discovery->discoverTools($base . '/AI');

        return response()->json([
            'data'  => $tools,
            'total' => count($tools),
            'ts'    => now()->toIso8601String(),
        ]);
    }

    /**
     * GET /api/v1/registry/workflows
     *
     * List all entries in the Workflow Registry.
     */
    public function workflows(): JsonResponse
    {
        $base      = dirname(__DIR__, 5);
        $workflows = $this->discovery->discoverWorkflows($base . '/AI');

        return response()->json([
            'data'  => $workflows,
            'total' => count($workflows),
            'ts'    => now()->toIso8601String(),
        ]);
    }

    /**
     * GET /api/v1/registry/agents
     *
     * List all entries in the Agent Registry.
     */
    public function agents(): JsonResponse
    {
        $base   = dirname(__DIR__, 5);
        $agents = $this->discovery->discoverAgents($base . '/AI');

        return response()->json([
            'data'  => $agents,
            'total' => count($agents),
            'ts'    => now()->toIso8601String(),
        ]);
    }

    /**
     * GET /api/v1/registry/prompts
     *
     * List all entries in the Prompt Registry.
     */
    public function prompts(): JsonResponse
    {
        $base    = dirname(__DIR__, 5);
        $prompts = $this->discovery->discoverPrompts($base . '/AI');

        return response()->json([
            'data'  => $prompts,
            'total' => count($prompts),
            'ts'    => now()->toIso8601String(),
        ]);
    }

    /**
     * POST /api/v1/registry/rebuild
     *
     * Rebuild the specified registry type (or all if not specified).
     * Clears relevant caches and re-runs discovery.
     */
    public function rebuild(): JsonResponse
    {
        $type  = request()->input('type', 'all');
        $steps = [];

        try {
            Artisan::call('cache:clear');
            $steps[] = ['step' => 'cache_clear', 'status' => 'ok'];
        } catch (\Throwable $e) {
            $steps[] = ['step' => 'cache_clear', 'status' => 'warning', 'detail' => $e->getMessage()];
        }

        try {
            Artisan::call('config:clear');
            $steps[] = ['step' => 'config_clear', 'status' => 'ok'];
        } catch (\Throwable $e) {
            $steps[] = ['step' => 'config_clear', 'status' => 'warning', 'detail' => $e->getMessage()];
        }

        // Re-discover assets after cache clear
        $base  = dirname(__DIR__, 5);
        $aiDir = $base . '/AI';
        $counts = [];

        if (in_array($type, ['all', 'providers'], true)) {
            $counts['providers'] = count($this->discovery->discoverProviders($aiDir));
        }
        if (in_array($type, ['all', 'tools'], true)) {
            $counts['tools'] = count($this->discovery->discoverTools($aiDir));
        }
        if (in_array($type, ['all', 'workflows'], true)) {
            $counts['workflows'] = count($this->discovery->discoverWorkflows($aiDir));
        }
        if (in_array($type, ['all', 'agents'], true)) {
            $counts['agents'] = count($this->discovery->discoverAgents($aiDir));
        }
        if (in_array($type, ['all', 'prompts'], true)) {
            $counts['prompts'] = count($this->discovery->discoverPrompts($aiDir));
        }

        return response()->json([
            'status'  => 'ok',
            'type'    => $type,
            'counts'  => $counts,
            'steps'   => $steps,
            'ts'      => now()->toIso8601String(),
        ]);
    }

    /**
     * POST /api/v1/registry/refresh
     *
     * Signal a lightweight registry refresh (cache clear only).
     */
    public function refresh(): JsonResponse
    {
        try {
            Artisan::call('cache:clear');
        } catch (\Throwable) {}

        return response()->json([
            'status'  => 'ok',
            'message' => 'Registry refresh triggered',
            'ts'      => now()->toIso8601String(),
        ]);
    }

    /**
     * GET /api/v1/registry/validate
     *
     * Validate all registry manifests.
     */
    public function validate(): JsonResponse
    {
        $output = '';

        try {
            Artisan::call('titan:validate-manifests');
            $output = Artisan::output();
            $status = 'ok';
        } catch (\Throwable $e) {
            $output = $e->getMessage();
            $status = 'error';
        }

        return response()->json([
            'status' => $status,
            'output' => $output,
            'ts'     => now()->toIso8601String(),
        ]);
    }

    /**
     * GET /api/v1/registry/export
     *
     * Export all registry data as a JSON payload.
     */
    public function export(): JsonResponse
    {
        $base  = dirname(__DIR__, 5);
        $aiDir = $base . '/AI';

        $export = [
            'platform'  => 'TitanCore',
            'exported_at' => now()->toIso8601String(),
            'providers' => $this->discovery->discoverProviders($aiDir),
            'tools'     => $this->discovery->discoverTools($aiDir),
            'workflows' => $this->discovery->discoverWorkflows($aiDir),
            'agents'    => $this->discovery->discoverAgents($aiDir),
            'prompts'   => $this->discovery->discoverPrompts($aiDir),
            'modules'   => $this->modules()->getData(true)['data'] ?? [],
        ];

        return response()->json($export);
    }

    // ── Private helpers ────────────────────────────────────────────────────────

    private function countModules(): int
    {
        $modulesDir = base_path('Modules');
        $count      = 0;

        if (is_dir($modulesDir)) {
            foreach (scandir($modulesDir) ?: [] as $entry) {
                if ($entry !== '.' && $entry !== '..' && is_dir($modulesDir . '/' . $entry)) {
                    $count++;
                }
            }
        }

        return $count;
    }

    private function readModuleMeta(string $dir, string $fallbackName): array
    {
        $name    = $fallbackName;
        $alias   = strtolower($fallbackName);
        $version = null;
        $desc    = null;
        $caps    = [];

        try {
            $mFile = $dir . '/module.json';
            if (is_file($mFile)) {
                $data    = json_decode((string) file_get_contents($mFile), true) ?: [];
                $name    = $data['name'] ?? $name;
                $alias   = $data['alias'] ?? $alias;
                $desc    = $data['description'] ?? null;
                $caps    = $data['capabilities'] ?? [];
                $version = $data['version'] ?? null;
            }
        } catch (\Throwable) {}

        return [
            'id'           => $alias,
            'name'         => $name,
            'alias'        => $alias,
            'version'      => $version,
            'description'  => $desc,
            'capabilities' => $caps,
        ];
    }
}
