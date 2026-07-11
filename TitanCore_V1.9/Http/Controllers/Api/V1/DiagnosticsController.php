<?php

namespace Modules\TitanCore\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Modules\TitanCore\Services\KbHealthService;

/**
 * Diagnostics API — /api/v1/diagnostics/*
 *
 * Provides operational diagnostics for system, providers, modules,
 * knowledge base, runtime, and storage layers.
 *
 * Reuses existing health services where possible.
 */
class DiagnosticsController extends Controller
{
    public function __construct(
        private readonly KbHealthService $kbHealth,
    ) {}

    /**
     * GET /api/v1/diagnostics
     *
     * Overall diagnostics summary.
     */
    public function index(): JsonResponse
    {
        $base    = dirname(__DIR__, 5);
        $version = null;
        try {
            $vFile = $base . DIRECTORY_SEPARATOR . 'version.txt';
            if (is_file($vFile)) {
                $version = trim((string) file_get_contents($vFile));
            }
        } catch (\Throwable) {}

        return response()->json([
            'platform'     => 'TitanCore',
            'version'      => $version,
            'environment'  => app()->environment(),
            'php'          => PHP_VERSION,
            'laravel'      => app()->version(),
            'debug'        => (bool) config('app.debug'),
            'config_cached'=> app()->configurationIsCached(),
            'routes_cached'=> app()->routesAreCached(),
            'ts'           => now()->toIso8601String(),
        ]);
    }

    /**
     * GET /api/v1/diagnostics/system
     *
     * System-level diagnostics: DB, cache, queue, storage.
     */
    public function system(): JsonResponse
    {
        $checks = [
            'database' => $this->checkDb(),
            'cache'    => $this->checkCache(),
            'queue'    => $this->checkQueue(),
            'storage'  => $this->checkStorage(),
        ];

        $statuses = array_column($checks, 'status');
        $overall  = in_array('critical', $statuses, true) ? 'critical'
            : (in_array('warning', $statuses, true) ? 'warning' : 'ok');

        return response()->json([
            'status' => $overall,
            'checks' => $checks,
            'ts'     => now()->toIso8601String(),
        ]);
    }

    /**
     * GET /api/v1/diagnostics/providers
     *
     * Provider configuration diagnostics.
     */
    public function providers(): JsonResponse
    {
        $checks = [];

        $openaiKey = config('titan-ai.providers.openai.api_key') ?? config('openai.api_key');
        $checks['openai'] = empty($openaiKey)
            ? ['status' => 'warning', 'message' => 'API key not configured']
            : ['status' => 'ok',      'message' => 'API key configured'];

        $titanBase = config('titancore.providers.titanai.base_url') ?? config('titancore.magicai.base_url');
        $checks['titanai'] = empty($titanBase)
            ? ['status' => 'warning', 'message' => 'Base URL not configured']
            : ['status' => 'ok',      'message' => 'Base URL configured'];

        $checks['elevenlabs'] = empty(config('services.elevenlabs.api_key'))
            ? ['status' => 'warning', 'message' => 'API key not configured']
            : ['status' => 'ok',      'message' => 'API key configured'];

        $statuses = array_column($checks, 'status');
        $overall  = in_array('critical', $statuses, true) ? 'critical'
            : (in_array('warning', $statuses, true) ? 'warning' : 'ok');

        return response()->json([
            'status' => $overall,
            'checks' => $checks,
            'ts'     => now()->toIso8601String(),
        ]);
    }

    /**
     * GET /api/v1/diagnostics/modules
     *
     * Module registration and status diagnostics.
     */
    public function modules(): JsonResponse
    {
        $modulesDir   = base_path('Modules');
        $discovered   = [];
        $statusFile   = base_path('modules_statuses.json');
        $statuses     = [];

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
                $dir  = $modulesDir . DIRECTORY_SEPARATOR . $entry;
                if (! is_dir($dir)) {
                    continue;
                }
                $discovered[] = [
                    'name'    => $entry,
                    'enabled' => (bool) ($statuses[$entry] ?? true),
                    'has_module_json' => is_file($dir . '/module.json'),
                    'has_composer_json' => is_file($dir . '/composer.json'),
                ];
            }
        }

        return response()->json([
            'modules' => $discovered,
            'total'   => count($discovered),
            'ts'      => now()->toIso8601String(),
        ]);
    }

    /**
     * GET /api/v1/diagnostics/knowledge
     *
     * Knowledge base health diagnostics.
     */
    public function knowledge(): JsonResponse
    {
        $summary = $this->kbHealth->summary();

        return response()->json([
            'status'  => empty($summary) ? 'warning' : 'ok',
            'summary' => $summary,
            'ts'      => now()->toIso8601String(),
        ]);
    }

    /**
     * GET /api/v1/diagnostics/runtime
     *
     * Runtime diagnostics: AI run log stats.
     */
    public function runtime(): JsonResponse
    {
        $stats = ['total' => 0, 'success' => 0, 'error' => 0, 'running' => 0];

        try {
            if (DB::getSchemaBuilder()->hasTable('ai_runs')) {
                $rows = DB::table('ai_runs')
                    ->selectRaw('status, COUNT(*) as cnt')
                    ->groupBy('status')
                    ->get();

                foreach ($rows as $row) {
                    $stats['total']                    += (int) $row->cnt;
                    $stats[$row->status ?? 'other']     = ($stats[$row->status ?? 'other'] ?? 0) + (int) $row->cnt;
                }
            }
        } catch (\Throwable) {}

        return response()->json([
            'stats' => $stats,
            'ts'    => now()->toIso8601String(),
        ]);
    }

    /**
     * GET /api/v1/diagnostics/storage
     *
     * Storage layer diagnostics.
     */
    public function storage(): JsonResponse
    {
        $check = $this->checkStorage();

        return response()->json([
            'status'  => $check['status'],
            'message' => $check['message'],
            'driver'  => config('filesystems.default', 'local'),
            'ts'      => now()->toIso8601String(),
        ]);
    }

    // ── Private check helpers ─────────────────────────────────────────────────

    private function checkDb(): array
    {
        try {
            DB::connection()->getPdo();
            return ['status' => 'ok', 'message' => 'Connected'];
        } catch (\Throwable $e) {
            return ['status' => 'critical', 'message' => 'Connection failed: ' . $e->getMessage()];
        }
    }

    private function checkCache(): array
    {
        try {
            $key = 'titan_diag_probe_' . uniqid('', true);
            Cache::put($key, 'ok', 5);
            $val = Cache::get($key);
            Cache::forget($key);

            return $val === 'ok'
                ? ['status' => 'ok', 'message' => 'Cache write/read OK']
                : ['status' => 'critical', 'message' => 'Cache round-trip mismatch'];
        } catch (\Throwable $e) {
            return ['status' => 'critical', 'message' => 'Cache check failed: ' . $e->getMessage()];
        }
    }

    private function checkQueue(): array
    {
        try {
            $size = Queue::size();
            if ($size >= 1000) {
                return ['status' => 'critical', 'message' => "Queue depth critical: {$size}"];
            }
            if ($size >= 100) {
                return ['status' => 'warning', 'message' => "Queue depth elevated: {$size}"];
            }
            return ['status' => 'ok', 'message' => "Queue depth: {$size}"];
        } catch (\Throwable) {
            return ['status' => 'ok', 'message' => 'Queue reachable (depth unavailable)'];
        }
    }

    private function checkStorage(): array
    {
        try {
            $disk  = Storage::disk(config('filesystems.default', 'local'));
            $probe = '.titan_diag_probe_' . uniqid('', true);
            $disk->put($probe, 'ok');
            $disk->delete($probe);
            return ['status' => 'ok', 'message' => 'Storage writable'];
        } catch (\Throwable $e) {
            return ['status' => 'critical', 'message' => 'Storage check failed: ' . $e->getMessage()];
        }
    }
}
