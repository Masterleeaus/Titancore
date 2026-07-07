<?php

namespace Modules\TitanCore\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TitanCoreUninstall extends Command
{
    protected $signature = 'titancore:uninstall {--force : Do not ask for confirmation}';
    protected $description = 'Remove TitanCore menus and permissions (non-destructive to data tables).';

    public function handle(): int
    {
        if (!$this->option('force')) {
            if (!$this->confirm('This will remove menu items and permissions for TitanCore. Continue?')) {
                $this->info('Aborted.');
                return self::SUCCESS;
            }
        }

        if (Schema::hasTable('menus')) {
            DB::table('menus')->where('slug', 'titancore')->delete();
            $this->info('Removed menus (menus.slug=titancore).');
        }
        if (Schema::hasTable('navigation')) {
            DB::table('navigation')->where('key', 'titancore')->delete();
            $this->info('Removed menus (navigation.key=titancore).');
        }
        if (Schema::hasTable('role_has_permissions') && Schema::hasTable('permissions')) {
            $permIds = DB::table('permissions')->whereIn('name', ['titancore.view','titancore.manage'])->pluck('id');
            if (count($permIds)) {
                DB::table('role_has_permissions')->whereIn('permission_id', $permIds)->delete();
            }
            DB::table('permissions')->whereIn('name', ['titancore.view','titancore.manage'])->delete();
            $this->info('Removed permissions and role mappings.');
        }

        $this->info('TitanCore uninstall tasks finished.');
        return self::SUCCESS;
    }
}
