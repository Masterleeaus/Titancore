<?php

namespace Modules\TitanCore\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

class MetricsController extends Controller
{
    public function usage(Request $request)
    {
        $start = $request->query('start', now()->subDays(14)->toDateString());
        $end   = $request->query('end', now()->toDateString());
        $tenantId = optional(auth()->user())->tenant_id ?? null;
        $key = $tenantId ? 'tenant:' . $tenantId : 'global';

        $rows = DB::table('ai_usage')
            ->select(['date','requests','tokens'])
            ->where('key', $key)
            ->whereBetween('date', [$start, $end])
            ->orderBy('date', 'asc')
            ->get();

        return response()->json([
            'key' => $key,
            'start' => $start,
            'end' => $end,
            'data' => $rows,
        ]);
    }

    public function metrics(Request $request)
    {
        $tenantId = optional(auth()->user())->tenant_id ?? null;
        $key = $tenantId ? 'tenant:' . $tenantId : 'global';

        $agg = DB::table('ai_usage')
            ->selectRaw('COALESCE(SUM(requests),0) as reqs, COALESCE(SUM(tokens),0) as toks')
            ->where('key', $key)
            ->first();

        $reqs = (int)($agg->reqs ?? 0);
        $toks = (int)($agg->toks ?? 0);

        $lines = [];
        $lines[] = '# HELP titancore_requests_total Total AI requests for key';
        $lines[] = '# TYPE titancore_requests_total counter';
        $lines[] = 'titancore_requests_total{key="' . $key . '"} ' . $reqs;
        $lines[] = '# HELP titancore_tokens_total Total AI tokens for key';
        $lines[] = '# TYPE titancore_tokens_total counter';
        $lines[] = 'titancore_tokens_total{key="' . $key . '"} ' . $toks;

        $body = implode("\n", $lines) . "\n";
        return response($body, 200)->header('Content-Type', 'text/plain; version=0.0.4');
    }
}
