<?php

namespace Modules\TitanCore\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

/**
 * Telemetry API — /api/v1/telemetry/*
 *
 * Exposes operational metrics: traces, metrics, costs, errors,
 * and per-provider statistics. Operational information only.
 */
class TelemetryController extends Controller
{
    /**
     * GET /api/v1/telemetry
     *
     * Return platform-level telemetry summary.
     */
    public function index(): JsonResponse
    {
        $summary = [
            'requests' => 0,
            'tokens'   => 0,
            'cost_usd' => null,
            'errors'   => 0,
        ];

        try {
            if (DB::getSchemaBuilder()->hasTable('ai_usage')) {
                $row = DB::table('ai_usage')
                    ->selectRaw('COALESCE(SUM(requests), 0) as requests, COALESCE(SUM(tokens), 0) as tokens')
                    ->first();

                $summary['requests'] = (int) ($row->requests ?? 0);
                $summary['tokens']   = (int) ($row->tokens ?? 0);
            }
        } catch (\Throwable) {}

        try {
            if (DB::getSchemaBuilder()->hasTable('ai_usage_costs')) {
                $cost = DB::table('ai_usage_costs')->sum('cost_usd');
                $summary['cost_usd'] = round((float) $cost, 6);
            }
        } catch (\Throwable) {}

        try {
            if (DB::getSchemaBuilder()->hasTable('ai_runs')) {
                $summary['errors'] = (int) DB::table('ai_runs')->where('status', 'error')->count();
            }
        } catch (\Throwable) {}

        return response()->json([
            'telemetry' => $summary,
            'ts'        => now()->toIso8601String(),
        ]);
    }

    /**
     * GET /api/v1/telemetry/traces
     *
     * Return recent execution traces from the AI runs log.
     */
    public function traces(Request $request): JsonResponse
    {
        $limit = (int) $request->query('limit', 50);
        $rows  = [];

        try {
            if (DB::getSchemaBuilder()->hasTable('ai_runs')) {
                $rows = DB::table('ai_runs')
                    ->orderBy('created_at', 'desc')
                    ->limit($limit)
                    ->get(['id', 'company_id', 'user_id', 'provider', 'action', 'model', 'status', 'created_at'])
                    ->toArray();
            }
        } catch (\Throwable) {}

        return response()->json([
            'data'  => $rows,
            'total' => count($rows),
            'ts'    => now()->toIso8601String(),
        ]);
    }

    /**
     * GET /api/v1/telemetry/metrics
     *
     * Return time-series usage metrics.
     */
    public function metrics(Request $request): JsonResponse
    {
        $start = $request->query('start', now()->subDays(14)->toDateString());
        $end   = $request->query('end', now()->toDateString());
        $rows  = [];

        try {
            if (DB::getSchemaBuilder()->hasTable('ai_usage')) {
                $rows = DB::table('ai_usage')
                    ->whereBetween('date', [$start, $end])
                    ->orderBy('date', 'asc')
                    ->get(['date', 'key', 'requests', 'tokens'])
                    ->toArray();
            }
        } catch (\Throwable) {}

        return response()->json([
            'data'  => $rows,
            'start' => $start,
            'end'   => $end,
            'ts'    => now()->toIso8601String(),
        ]);
    }

    /**
     * GET /api/v1/telemetry/costs
     *
     * Return cost breakdown by model/provider.
     */
    public function costs(Request $request): JsonResponse
    {
        $rows = [];

        try {
            if (DB::getSchemaBuilder()->hasTable('ai_usage_costs')) {
                $rows = DB::table('ai_usage_costs')
                    ->orderBy('created_at', 'desc')
                    ->limit((int) $request->query('limit', 100))
                    ->get()
                    ->toArray();
            }
        } catch (\Throwable) {}

        return response()->json([
            'data'  => $rows,
            'total' => count($rows),
            'ts'    => now()->toIso8601String(),
        ]);
    }

    /**
     * GET /api/v1/telemetry/errors
     *
     * Return recent error traces.
     */
    public function errors(Request $request): JsonResponse
    {
        $limit = (int) $request->query('limit', 50);
        $rows  = [];

        try {
            if (DB::getSchemaBuilder()->hasTable('ai_runs')) {
                $rows = DB::table('ai_runs')
                    ->where('status', 'error')
                    ->orderBy('created_at', 'desc')
                    ->limit($limit)
                    ->get(['id', 'company_id', 'user_id', 'provider', 'action', 'model', 'status', 'created_at'])
                    ->toArray();
            }
        } catch (\Throwable) {}

        return response()->json([
            'data'  => $rows,
            'total' => count($rows),
            'ts'    => now()->toIso8601String(),
        ]);
    }

    /**
     * GET /api/v1/telemetry/providers
     *
     * Return per-provider telemetry aggregates.
     */
    public function providers(): JsonResponse
    {
        $rows = [];

        try {
            if (DB::getSchemaBuilder()->hasTable('ai_runs')) {
                $rows = DB::table('ai_runs')
                    ->selectRaw('provider, status, COUNT(*) as cnt')
                    ->groupBy(['provider', 'status'])
                    ->orderBy('provider')
                    ->get()
                    ->toArray();
            }
        } catch (\Throwable) {}

        // Reshape into provider-keyed map
        $byProvider = [];
        foreach ($rows as $row) {
            $p = $row->provider ?? 'unknown';
            if (! isset($byProvider[$p])) {
                $byProvider[$p] = ['provider' => $p, 'total' => 0, 'success' => 0, 'error' => 0];
            }
            $byProvider[$p]['total']             += (int) $row->cnt;
            $byProvider[$p][$row->status ?? 'other'] = ($byProvider[$p][$row->status ?? 'other'] ?? 0) + (int) $row->cnt;
        }

        return response()->json([
            'data' => array_values($byProvider),
            'ts'   => now()->toIso8601String(),
        ]);
    }
}
