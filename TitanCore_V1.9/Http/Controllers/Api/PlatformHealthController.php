<?php

namespace Modules\TitanCore\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

/**
 * Platform-wide health check endpoint.
 *
 * GET /api/v1/platform/health  (auth + super-admin only)
 *
 * Response shape:
 * {
 *   "status": "ok|warning|critical",
 *   "checks": {
 *     "database":  { "status": "ok", "message": "Connected" },
 *     "cache":     { "status": "ok", "message": "Cache write/read OK" },
 *     "queue":     { "status": "warning", "message": "Queue depth: 253" },
 *     "storage":   { "status": "ok", "message": "Writable" },
 *     "openai":    { "status": "ok", "message": "API key configured" },
 *     "elevenlabs":{ "status": "warning", "message": "API key not configured" },
 *     "twilio":    { "status": "warning", "message": "Credentials not configured" }
 *   },
 *   "ts": "2026-01-01T00:00:00+00:00"
 * }
 *
 * Dispatches TitanCore.HealthCheckFailed when any check is critical.
 */
class PlatformHealthController extends BaseController
{
    /** Queue depth threshold above which the check is demoted to 'warning' */
    private const QUEUE_DEPTH_WARNING   = 100;
    private const QUEUE_DEPTH_CRITICAL  = 1000;

    public function __invoke(): JsonResponse
    {
        $checks = [
            'database'   => $this->checkDatabase(),
            'cache'      => $this->checkCache(),
            'queue'      => $this->checkQueue(),
            'storage'    => $this->checkStorage(),
            'openai'     => $this->checkOpenAI(),
            'elevenlabs' => $this->checkElevenLabs(),
            'twilio'     => $this->checkTwilio(),
        ];

        $overallStatus = $this->aggregateStatus($checks);

        if ($overallStatus === 'critical') {
            $this->dispatchHealthCheckFailed($checks);
        }

        return response()->json([
            'status' => $overallStatus,
            'checks' => $checks,
            'ts'     => now()->toIso8601String(),
        ]);
    }

    // ── Individual checks ─────────────────────────────────────────────────────

    /** @return array{status: string, message: string} */
    private function checkDatabase(): array
    {
        try {
            DB::connection()->getPdo();

            return ['status' => 'ok', 'message' => 'Connected'];
        } catch (\Throwable $e) {
            return ['status' => 'critical', 'message' => 'Database connection failed: '.$e->getMessage()];
        }
    }

    /** @return array{status: string, message: string} */
    private function checkCache(): array
    {
        try {
            $key   = 'titan_health_probe_'.uniqid('', true);
            $value = 'ping';

            Cache::put($key, $value, 5);
            $retrieved = Cache::get($key);
            Cache::forget($key);

            if ($retrieved !== $value) {
                return ['status' => 'critical', 'message' => 'Cache round-trip mismatch'];
            }

            return ['status' => 'ok', 'message' => 'Cache write/read OK'];
        } catch (\Throwable $e) {
            return ['status' => 'critical', 'message' => 'Cache check failed: '.$e->getMessage()];
        }
    }

    /** @return array{status: string, message: string} */
    private function checkQueue(): array
    {
        try {
            // Attempt to read the depth of the default queue.
            // Not all drivers expose size(); fall back gracefully.
            $size = Queue::size();

            if ($size >= self::QUEUE_DEPTH_CRITICAL) {
                return ['status' => 'critical', 'message' => "Queue depth critical: {$size}"];
            }

            if ($size >= self::QUEUE_DEPTH_WARNING) {
                return ['status' => 'warning', 'message' => "Queue depth elevated: {$size}"];
            }

            return ['status' => 'ok', 'message' => "Queue depth: {$size}"];
        } catch (\Throwable) {
            // Driver does not support size() — treat as unknown/ok
            return ['status' => 'ok', 'message' => 'Queue reachable (depth unavailable for this driver)'];
        }
    }

    /** @return array{status: string, message: string} */
    private function checkStorage(): array
    {
        try {
            $disk = Storage::disk(config('filesystems.default', 'local'));

            $probe = '.titan_health_probe_'.uniqid('', true);
            $disk->put($probe, 'ok');
            $disk->delete($probe);

            return ['status' => 'ok', 'message' => 'Storage writable'];
        } catch (\Throwable $e) {
            return ['status' => 'critical', 'message' => 'Storage check failed: '.$e->getMessage()];
        }
    }

    /** @return array{status: string, message: string} */
    private function checkOpenAI(): array
    {
        $key = config('titan-ai.providers.openai.api_key')
            ?? config('openai.api_key');

        if (empty($key)) {
            return ['status' => 'warning', 'message' => 'OpenAI API key not configured'];
        }

        return ['status' => 'ok', 'message' => 'OpenAI API key configured'];
    }

    /** @return array{status: string, message: string} */
    private function checkElevenLabs(): array
    {
        $key = config('services.elevenlabs.api_key');

        if (empty($key)) {
            return ['status' => 'warning', 'message' => 'ElevenLabs API key not configured'];
        }

        return ['status' => 'ok', 'message' => 'ElevenLabs API key configured'];
    }

    /** @return array{status: string, message: string} */
    private function checkTwilio(): array
    {
        $sid   = config('services.twilio.account_sid');
        $token = config('services.twilio.auth_token');

        if (empty($sid) || empty($token)) {
            return ['status' => 'warning', 'message' => 'Twilio credentials not configured'];
        }

        return ['status' => 'ok', 'message' => 'Twilio credentials configured'];
    }

    // ── Aggregation ───────────────────────────────────────────────────────────

    /**
     * Roll up all per-check statuses into a single overall status.
     *
     * Priority: critical > warning > ok
     *
     * @param  array<string, array{status: string, message: string}>  $checks
     */
    private function aggregateStatus(array $checks): string
    {
        $statuses = array_column($checks, 'status');

        if (in_array('critical', $statuses, true)) {
            return 'critical';
        }

        if (in_array('warning', $statuses, true)) {
            return 'warning';
        }

        return 'ok';
    }

    private function dispatchHealthCheckFailed(array $checks): void
    {
        try {
            Event::dispatch('TitanCore.HealthCheckFailed', [
                'check'  => 'platform_health',
                'checks' => $checks,
                'ts'     => now()->toIso8601String(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('TitanCore.PlatformHealth: could not dispatch HealthCheckFailed event', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
