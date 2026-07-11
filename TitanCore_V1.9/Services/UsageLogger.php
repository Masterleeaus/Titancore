<?php

namespace Modules\TitanCore\Services;

use Illuminate\Support\Facades\DB;

class UsageLogger
{
    public static function add(string $key, int $tokens = 0, int $requests = 1): void
    {
        $today = now()->toDateString();
        $exists = DB::table('ai_usage')->where('key',$key)->whereDate('date',$today)->exists();
        if (!$exists) {
            DB::table('ai_usage')->insert([
                'key' => $key,
                'date' => $today,
                'requests' => max(0,$requests),
                'tokens' => max(0,$tokens),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('ai_usage')->where('key',$key)->whereDate('date',$today)->incrementEach([
                'requests' => max(0,$requests),
                'tokens' => max(0,$tokens),
            ]);
        }
    }
}
