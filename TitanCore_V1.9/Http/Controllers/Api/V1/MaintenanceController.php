<?php

namespace Modules\TitanCore\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Maintenance API — /api/v1/maintenance/*
 *
 * Manages Laravel maintenance mode for the platform.
 * Enable/disable maintenance mode and retrieve current status.
 */
class MaintenanceController extends Controller
{
    /**
     * GET /api/v1/maintenance
     *
     * Return the current maintenance mode status and any active message/retry config.
     */
    public function index(): JsonResponse
    {
        $down    = app()->isDownForMaintenance();
        $payload = [];

        if ($down) {
            $storagePath = storage_path('framework/down');
            try {
                if (is_file($storagePath)) {
                    $payload = json_decode((string) file_get_contents($storagePath), true) ?: [];
                }
            } catch (\Throwable) {}
        }

        return response()->json([
            'maintenance' => $down,
            'message'     => $payload['message'] ?? null,
            'retry'       => $payload['retry'] ?? null,
            'secret'      => isset($payload['secret']) ? '[configured]' : null,
            'allowed'     => $payload['allowed'] ?? [],
            'ts'          => now()->toIso8601String(),
        ]);
    }

    /**
     * POST /api/v1/maintenance/enable
     *
     * Put the application into maintenance mode.
     *
     * Accepts:
     *   - message (string): Human-readable maintenance message
     *   - retry   (int):    Retry-After seconds hint
     *   - secret  (string): Bypass token (allows access via /?secret=<token>)
     */
    public function enable(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message' => 'nullable|string|max:500',
            'retry'   => 'nullable|integer|min:0',
            'secret'  => 'nullable|string|max:120',
        ]);

        $args = ['--' => true];  // Artisan expects `--` for no-interaction

        if (! empty($validated['message'])) {
            $args['--message'] = $validated['message'];
        }
        if (isset($validated['retry'])) {
            $args['--retry'] = (int) $validated['retry'];
        }
        if (! empty($validated['secret'])) {
            $args['--secret'] = $validated['secret'];
        }

        try {
            \Illuminate\Support\Facades\Artisan::call('down', array_filter($args, fn ($v) => $v !== true));

            return response()->json([
                'ok'          => true,
                'maintenance' => true,
                'message'     => $validated['message'] ?? 'Application is in maintenance mode.',
                'ts'          => now()->toIso8601String(),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'ok'    => false,
                'error' => $e->getMessage(),
                'ts'    => now()->toIso8601String(),
            ], 500);
        }
    }

    /**
     * POST /api/v1/maintenance/disable
     *
     * Take the application out of maintenance mode.
     */
    public function disable(): JsonResponse
    {
        if (! app()->isDownForMaintenance()) {
            return response()->json([
                'ok'          => true,
                'maintenance' => false,
                'message'     => 'Application is already live.',
                'ts'          => now()->toIso8601String(),
            ]);
        }

        try {
            \Illuminate\Support\Facades\Artisan::call('up');

            return response()->json([
                'ok'          => true,
                'maintenance' => false,
                'message'     => 'Application is now live.',
                'ts'          => now()->toIso8601String(),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'ok'    => false,
                'error' => $e->getMessage(),
                'ts'    => now()->toIso8601String(),
            ], 500);
        }
    }
}
