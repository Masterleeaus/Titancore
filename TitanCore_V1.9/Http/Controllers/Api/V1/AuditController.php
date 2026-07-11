<?php

namespace Modules\TitanCore\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Audit API — /api/v1/audit/*
 *
 * Exposes the platform audit trail backed by `titan_tool_audit_log`.
 * Read-only: audit records are append-only and must not be modified
 * or deleted via the API to preserve integrity.
 */
class AuditController extends Controller
{
    private const TOOL_LOG_TABLE = 'titan_tool_audit_log';

    /**
     * GET /api/v1/audit
     *
     * Return a paginated audit trail summary across all sources.
     */
    public function index(Request $request): JsonResponse
    {
        $limit = min((int) $request->query('limit', 50), 200);
        $rows  = [];
        $total = 0;

        if (Schema::hasTable(self::TOOL_LOG_TABLE)) {
            try {
                $query = DB::table(self::TOOL_LOG_TABLE)->orderByDesc('created_at');

                if ($request->filled('status')) {
                    $query->where('status', $request->query('status'));
                }
                if ($request->filled('tool')) {
                    $query->where('tool', $request->query('tool'));
                }
                if ($request->filled('user_id')) {
                    $query->where('user_id', (int) $request->query('user_id'));
                }

                $total = $query->count();
                $rows  = $query->limit($limit)
                    ->get(['id', 'tool', 'user_id', 'company_id', 'status', 'duration_ms', 'error', 'created_at'])
                    ->toArray();
            } catch (\Throwable) {}
        }

        return response()->json([
            'data'  => $rows,
            'total' => $total,
            'limit' => $limit,
            'ts'    => now()->toIso8601String(),
        ]);
    }

    /**
     * GET /api/v1/audit/tools
     *
     * Return tool execution audit records with per-tool aggregates.
     */
    public function tools(Request $request): JsonResponse
    {
        $limit  = min((int) $request->query('limit', 50), 200);
        $rows   = [];
        $totals = [];

        if (Schema::hasTable(self::TOOL_LOG_TABLE)) {
            try {
                // Per-tool aggregates
                $agg = DB::table(self::TOOL_LOG_TABLE)
                    ->selectRaw('tool, status, COUNT(*) as cnt, AVG(duration_ms) as avg_ms')
                    ->groupBy(['tool', 'status'])
                    ->orderBy('tool')
                    ->get();

                foreach ($agg as $row) {
                    $t = $row->tool ?? 'unknown';
                    if (! isset($totals[$t])) {
                        $totals[$t] = ['tool' => $t, 'total' => 0, 'by_status' => []];
                    }
                    $totals[$t]['total']                          += (int) $row->cnt;
                    $totals[$t]['by_status'][$row->status ?? 'other'] = [
                        'count'  => (int) $row->cnt,
                        'avg_ms' => $row->avg_ms !== null ? round((float) $row->avg_ms, 2) : null,
                    ];
                }

                // Recent individual records
                $rows = DB::table(self::TOOL_LOG_TABLE)
                    ->orderByDesc('created_at')
                    ->limit($limit)
                    ->get(['id', 'tool', 'user_id', 'company_id', 'status', 'duration_ms', 'error', 'created_at'])
                    ->toArray();
            } catch (\Throwable) {}
        }

        return response()->json([
            'aggregates' => array_values($totals),
            'recent'     => $rows,
            'ts'         => now()->toIso8601String(),
        ]);
    }

    /**
     * GET /api/v1/audit/export
     *
     * Export the full audit trail as a JSON dataset.
     * Supports date range filtering via `from` and `to` query params (YYYY-MM-DD).
     */
    public function export(Request $request): JsonResponse
    {
        $from  = $request->query('from', now()->subDays(30)->toDateString());
        $to    = $request->query('to', now()->toDateString());
        $rows  = [];

        if (Schema::hasTable(self::TOOL_LOG_TABLE)) {
            try {
                $rows = DB::table(self::TOOL_LOG_TABLE)
                    ->whereBetween('created_at', [$from . ' 00:00:00', $to . ' 23:59:59'])
                    ->orderBy('created_at')
                    ->get()
                    ->toArray();
            } catch (\Throwable) {}
        }

        return response()->json([
            'from'  => $from,
            'to'    => $to,
            'data'  => $rows,
            'total' => count($rows),
            'ts'    => now()->toIso8601String(),
        ]);
    }
}
