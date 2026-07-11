<?php

namespace Modules\TitanCore\Http\Controllers;

use Illuminate\Routing\Controller as BaseController;
use Modules\TitanCore\Services\TitanCoreModelGateway;

class HealthController extends BaseController
{
    public function __construct(private TitanCoreModelGateway $gateway) {}

    /**
     * Render the HTML health page for Titan Core.
     */
    public function index()
    {
        try {
            $health = $this->gateway->health();
        } catch (\Throwable $e) {
            $health = [
                'ok'       => false,
                'provider' => config('titancore.default_provider'),
                'reason'   => $e->getMessage(),
            ];
        }

        return view('titancore::health', [
            'provider' => config('titancore.default_provider'),
            'health'   => $health,
        ]);
    }

    /**
     * JSON status endpoint used by other modules / frontends.
     */
    public function status()
    {
        try {
            $health = $this->gateway->health();
        } catch (\Throwable $e) {
            $health = [
                'ok'       => false,
                'provider' => config('titancore.default_provider'),
                'reason'   => $e->getMessage(),
            ];
        }

        return response()->json([
            'ok'       => (bool) ($health['ok'] ?? false),
            'provider' => $health['provider'] ?? config('titancore.default_provider'),
            'health'   => $health,
            'ts'       => now()->toIso8601String(),
        ]);
    }


    /**
     * Simple diagnostics endpoint for Super Admin validation.
     */
    public function doctor()
    {
        $checks = [
            'module' => 'TitanCore',
            'provider_loaded' => class_exists(\Modules\TitanCore\Providers\TitanCoreServiceProvider::class),
            'routes_prefix' => 'titancore',
            'has_permissions_table' => \Illuminate\Support\Facades\Schema::hasTable('permissions'),
            'has_modules_table' => \Illuminate\Support\Facades\Schema::hasTable('modules'),
        ];

        // Best-effort: list permission keys that TitanCore expects (if table exists)
        if ($checks['has_permissions_table']) {
            try {
                $checks['permissions_found'] = \Illuminate\Support\Facades\DB::table('permissions')
                    ->whereIn('name', ['manage_ai','view_ai_usage','manage_ai_prompts','publish_ai_prompts','manage_ai_kb','ingest_ai_kb','use_ai_features'])
                    ->pluck('name')
                    ->values()
                    ->all();
            } catch (\Throwable $e) {
                $checks['permissions_error'] = $e->getMessage();
            }
        }

        return response()->json($checks);
    }

}
