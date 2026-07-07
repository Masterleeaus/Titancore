<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('modules')) {
            return;
        }

        // Work out which boolean column this Worksuite version uses
        $columns = Schema::getColumnListing('modules');

        $data = [
            'module_name' => 'Titan Core',
            'description' => 'Titan Core – unified AI engine for Worksuite modules',
            'created_at'  => now(),
            'updated_at'  => now(),
        ];

        if (in_array('status', $columns, true)) {
            $data['status'] = 1;
        } elseif (in_array('enabled', $columns, true)) {
            $data['enabled'] = 1;
        } elseif (in_array('is_active', $columns, true)) {
            $data['is_active'] = 1;
        }

        if (Schema::hasTable('modules')) {
        DB::table('modules')->updateOrInsert(
            ['module_name' => 'Titan Core'],
            $data
        );
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('modules')) {
            return;
        }

        DB::table('modules')
            ->where('module_name', 'Titan Core')
            ->delete();
    }
};
