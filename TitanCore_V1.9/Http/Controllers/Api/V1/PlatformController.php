<?php

namespace Modules\TitanCore\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Platform API — /api/v1/platform/*
 *
 * Exposes platform metadata, status, and operational information only.
 * No business logic is contained here.
 */
class PlatformController extends Controller
{
    private function moduleMeta(): array
    {
        $base    = dirname(__DIR__, 5);
        $version = null;

        try {
            $vFile = $base . DIRECTORY_SEPARATOR . 'version.txt';
            if (is_file($vFile)) {
                $version = trim((string) file_get_contents($vFile));
            }
        } catch (\Throwable) {}

        $moduleJson = [];
        try {
            $mFile = $base . DIRECTORY_SEPARATOR . 'module.json';
            if (is_file($mFile)) {
                $moduleJson = json_decode((string) file_get_contents($mFile), true) ?: [];
            }
        } catch (\Throwable) {}

        return ['version' => $version, 'module' => $moduleJson];
    }

    /**
     * GET /api/v1/platform/info
     *
     * Returns general platform identity information.
     */
    public function info(): JsonResponse
    {
        $meta = $this->moduleMeta();

        return response()->json([
            'platform'     => 'TitanCore',
            'version'      => $meta['version'] ?? 'unknown',
            'name'         => $meta['module']['name'] ?? 'TitanCore',
            'alias'        => $meta['module']['alias'] ?? 'titancore',
            'description'  => $meta['module']['description'] ?? null,
            'capabilities' => $meta['module']['capabilities'] ?? [],
            'environment'  => app()->environment(),
            'php'          => PHP_VERSION,
            'laravel'      => app()->version(),
            'ts'           => now()->toIso8601String(),
        ]);
    }

    /**
     * GET /api/v1/platform/status
     *
     * Returns a lightweight liveness status without deep checks.
     */
    public function status(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'platform' => 'TitanCore',
            'ts'     => now()->toIso8601String(),
        ]);
    }

    /**
     * GET /api/v1/platform/version
     *
     * Returns detailed version information.
     */
    public function version(): JsonResponse
    {
        $meta = $this->moduleMeta();

        return response()->json([
            'version'         => $meta['version'] ?? 'unknown',
            'module_version'  => $meta['module']['version'] ?? 'unknown',
            'php'             => PHP_VERSION,
            'laravel'         => app()->version(),
            'ts'              => now()->toIso8601String(),
        ]);
    }

    /**
     * GET /api/v1/platform/config
     *
     * Returns sanitised platform configuration (no secrets).
     */
    public function config(): JsonResponse
    {
        return response()->json([
            'environment'     => app()->environment(),
            'debug'           => (bool) config('app.debug'),
            'config_cached'   => app()->configurationIsCached(),
            'routes_cached'   => app()->routesAreCached(),
            'timezone'        => config('app.timezone', 'UTC'),
            'locale'          => config('app.locale', 'en'),
            'queue_driver'    => config('queue.default', 'sync'),
            'cache_driver'    => config('cache.default', 'file'),
            'session_driver'  => config('session.driver', 'file'),
            'mail_driver'     => config('mail.default', 'smtp'),
            'providers'       => [
                'openai_configured'     => (bool) (config('titan-ai.providers.openai.api_key') ?? config('openai.api_key')),
                'elevenlabs_configured' => (bool) config('services.elevenlabs.api_key'),
                'twilio_configured'     => (bool) (config('services.twilio.account_sid') && config('services.twilio.auth_token')),
                'magicai_configured'    => (bool) config('titancore.magicai.base_url'),
            ],
            'limits'          => [
                'daily_token_limit' => (int) config('titancore.daily_token_limit', 200000),
            ],
        ]);
    }

    /**
     * GET /api/v1/platform/features
     *
     * Returns the list of platform-level capabilities/features.
     */
    public function features(): JsonResponse
    {
        $meta         = $this->moduleMeta();
        $capabilities = $meta['module']['capabilities'] ?? [];

        return response()->json([
            'features' => $capabilities,
            'count'    => count($capabilities),
            'ts'       => now()->toIso8601String(),
        ]);
    }

    /**
     * GET /api/v1/platform/cache
     *
     * Returns cache health and basic statistics.
     */
    public function cache(): JsonResponse
    {
        $driver = config('cache.default', 'file');
        $status = 'ok';
        $message = 'Cache reachable';

        try {
            $probe = 'titan_platform_cache_probe_' . uniqid('', true);
            Cache::put($probe, 'ping', 5);
            $val = Cache::get($probe);
            Cache::forget($probe);
            if ($val !== 'ping') {
                $status  = 'warning';
                $message = 'Cache round-trip mismatch';
            }
        } catch (\Throwable $e) {
            $status  = 'critical';
            $message = 'Cache unavailable: ' . $e->getMessage();
        }

        return response()->json([
            'status'  => $status,
            'driver'  => $driver,
            'message' => $message,
            'ts'      => now()->toIso8601String(),
        ]);
    }

    /**
     * GET /api/v1/platform/telemetry
     *
     * Returns platform-level operational telemetry (token usage, request counts).
     */
    public function telemetry(): JsonResponse
    {
        $aggregate = ['requests' => 0, 'tokens' => 0];

        try {
            if (DB::getSchemaBuilder()->hasTable('ai_usage')) {
                $row = DB::table('ai_usage')
                    ->selectRaw('COALESCE(SUM(requests), 0) as requests, COALESCE(SUM(tokens), 0) as tokens')
                    ->first();

                $aggregate['requests'] = (int) ($row->requests ?? 0);
                $aggregate['tokens']   = (int) ($row->tokens ?? 0);
            }
        } catch (\Throwable) {}

        return response()->json([
            'telemetry' => $aggregate,
            'ts'        => now()->toIso8601String(),
        ]);
    }
}
