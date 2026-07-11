<?php

namespace Modules\TitanCore\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $today  = Carbon::today();
        $last7  = $today->copy()->subDays(6);
        $last30 = $today->copy()->subDays(29);

        $usageLast7 = DB::table('ai_usage')
            ->whereDate('date', '>=', $last7)
            ->sum('total_tokens');

        $usageLast30 = DB::table('ai_usage')
            ->whereDate('date', '>=', $last30)
            ->sum('total_tokens');

        $usageByModule = DB::table('ai_usage_ledger')
            ->select('module', DB::raw('SUM(total_tokens) as tokens'))
            ->whereDate('created_at', '>=', $last30)
            ->groupBy('module')
            ->orderByDesc('tokens')
            ->limit(10)
            ->get();

        $usageByModel = DB::table('ai_usage_ledger')
            ->select('model', DB::raw('SUM(total_tokens) as tokens'))
            ->whereDate('created_at', '>=', $last30)
            ->groupBy('model')
            ->orderByDesc('tokens')
            ->limit(10)
            ->get();

        $timeseries = DB::table('ai_usage')
            ->select('date', 'total_tokens')
            ->whereDate('date', '>=', $today->copy()->subDays(13))
            ->orderBy('date')
            ->get();

        return view('titancore::admin.dashboard.index', compact(
            'usageLast7',
            'usageLast30',
            'usageByModule',
            'usageByModel',
            'timeseries'
        ));
    }
}
