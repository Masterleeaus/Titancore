<?php

namespace Modules\TitanCore\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TitanCoreDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // --- Permissions (idempotent) ---
        if (Schema::hasTable('permissions')) {
            try {
                DB::table('permissions')->updateOrInsert(['name' => 'titancore.view'], ['guard_name' => 'web']);
                DB::table('permissions')->updateOrInsert(['name' => 'titancore.manage'], ['guard_name' => 'web']);
            } catch (\Throwable $e) {}
        }

        // --- Roles linkage (optional; only if roles/role_has_permissions exist) ---
        if (Schema::hasTable('roles') && Schema::hasTable('role_has_permissions') && Schema::hasTable('permissions')) {
            try {
                $permIds = DB::table('permissions')->whereIn('name', ['titancore.view','titancore.manage'])->pluck('id','name');
                $adminRoleIds = DB::table('roles')->whereIn('name', ['admin','super_admin'])->pluck('id');
                foreach ($adminRoleIds as $rid) {
                    foreach ($permIds as $pid) {
                        DB::table('role_has_permissions')->updateOrInsert(['permission_id' => $pid, 'role_id' => $rid], []);
                    }
                }
            } catch (\Throwable $e) {}
        }

        // --- Menus (idempotent; adapt to your host's schema) ---
        if (Schema::hasTable('menus')) {
            try {
                DB::table('menus')->updateOrInsert(
                    ['slug' => 'titancore'],
                    [
                        'title' => 'Titan Core',
                        'url'   => '/titancore/health',
                        'icon'  => 'fa-robot',
                        'parent_id' => null,
                        'order' => 50
                    ]
                );
            } catch (\Throwable $e) {}
        } elseif (Schema::hasTable('navigation')) {
            try {
                DB::table('navigation')->updateOrInsert(
                    ['key' => 'titancore'],
                    [
                        'label' => 'Titan Core',
                        'route' => 'titancore.health',
                        'icon'  => 'fa-robot',
                        'position' => 50
                    ]
                );
            } catch (\Throwable $e) {}
        }
    
        // --- Demo data (guarded) ---
        if (Schema::hasTable('ai_prompts')) {
            try {
                $exists = DB::table('ai_prompts')->where('slug','hello-world')->exists();
                if (!$exists) {
                    DB::table('ai_prompts')->insert([
                        'title' => 'Hello World',
                        'slug' => 'hello-world',
                        'content' => 'Say hello to the universe, politely.',
                        'tenant_id' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            } catch (\Throwable $e) {}
        }
    }
}
