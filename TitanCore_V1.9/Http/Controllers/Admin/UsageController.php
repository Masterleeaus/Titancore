<?php

namespace Modules\TitanCore\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class UsageController extends Controller
{
    public function index()
    {
        $now     = Carbon::now();
        $since7  = $now->copy()->subDays(7);
        $since30 = $now->copy()->subDays(30);

        $sum = function ($since) {
            return DB::table('ai_usage_ledger')
                ->where('created_at', '>=', $since)
                ->selectRaw('
                    COUNT(*) as requests,
                    SUM(tokens_in) as tokens_in,
                    SUM(tokens_out) as tokens_out,
                    SUM(cost) as cost,
                    SUM(CASE WHEN status = "error" THEN 1 ELSE 0 END) as errors
                ')
                ->first();
        };

        $s7  = $sum($since7);
        $s30 = $sum($since30);

        $recent = DB::table('ai_usage_ledger')
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        $byOp = DB::table('ai_usage_ledger')
            ->selectRaw('module, operation, COUNT(*) as cnt, SUM(cost) as cost, SUM(tokens_in + tokens_out) as tokens')
            ->groupBy('module', 'operation')
            ->orderByDesc('cnt')
            ->limit(20)
            ->get();

        return view('titancore::admin.usage.index', compact('s7', 's30', 'recent', 'byOp'));
    }
}
