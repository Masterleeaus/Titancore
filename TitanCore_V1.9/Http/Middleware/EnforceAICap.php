<?php

namespace Modules\TitanCore\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EnforceAICap
{
    public function handle(Request $request, Closure $next)
    {
        $cap = (int) config('titancore.quotas.per_tenant_daily_tokens', 200000);
        $tenantId = optional(auth()->user())->tenant_id ?? null;
        $key = $tenantId ? 'tenant:' . $tenantId : 'global';

        // naive usage counter table 'ai_usage' expected
        try {
            $today = now()->toDateString();
            $row = DB::table('ai_usage')->where('key', $key)->whereDate('date', $today)->first();
            $count = $row ? (int)($row->requests ?? 0) : 0;
            if ($count >= $cap) {
                return response()->json(['ok' => false, 'error' => 'AI daily cap reached'], 429);
            }
        } catch (\Throwable $e) {
            // fail-open for now
        }

        $response = $next($request);

        // Increment counter (1 request = proxy for tokens unless instrumented)
        try {
            $today = now()->toDateString();
            $exists = DB::table('ai_usage')->where('key', $key)->whereDate('date', $today)->exists();
            if (!$exists) {
                DB::table('ai_usage')->insert([
                    'key' => $key,
                    'date' => $today,
                    'requests' => 1,
                    'tokens' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('ai_usage')->where('key', $key)->whereDate('date', $today)->increment('requests', 1);
            }
        } catch (\Throwable $e) {}

        return $response;
    }
}
