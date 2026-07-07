<?php

namespace Modules\TitanCore\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TitanCoreDoctor extends Command
{
    protected $signature = 'titancore:doctor';
    protected $description = 'Diagnose TitanCore installation (routes, permissions, tables).';

    public function handle(): int
    {
        $this->info('TitanCore Doctor');

        $this->line('permissions table: ' . (Schema::hasTable('permissions') ? 'YES' : 'NO'));
        $this->line('modules table: ' . (Schema::hasTable('modules') ? 'YES' : 'NO'));

        if (Schema::hasTable('permissions')) {
            try {
                $found = DB::table('permissions')
                    ->whereIn('name', [
                        'manage_ai','view_ai_usage','manage_ai_prompts','publish_ai_prompts',
                        'manage_ai_kb','ingest_ai_kb','use_ai_features'
                    ])
                    ->pluck('name')->values()->all();
                $this->line('TitanCore permissions found: ' . implode(', ', $found));
            } catch (\Throwable $e) {
                $this->error('permissions query error: ' . $e->getMessage());
            }
        }

        $this->line('If routes are loaded, you should see titancore.* in `php artisan route:list`.');
        return self::SUCCESS;
    }
}
