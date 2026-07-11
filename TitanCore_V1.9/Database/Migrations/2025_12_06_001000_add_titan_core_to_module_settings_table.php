<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Determine correct column name for setting key
        if (Schema::hasColumn('module_settings', 'setting_name')) {
            $keyColumn = 'setting_name';
        } elseif (Schema::hasColumn('module_settings', 'key')) {
            $keyColumn = 'key';
        } elseif (Schema::hasColumn('module_settings', 'name')) {
            $keyColumn = 'name';
        } else {
            // Nothing we can do safely
            return;
        }

        $exists = DB::table('module_settings')
            ->where('module_name', 'Titan Core')
            ->where($keyColumn, 'status')
            ->exists();

        if (!$exists) {
            DB::table('module_settings')->insert([
                'module_name' => 'Titan Core',
                $keyColumn    => 'status',
                'value'       => 'enabled',
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('module_settings', 'setting_name')) {
            $keyColumn = 'setting_name';
        } elseif (Schema::hasColumn('module_settings', 'key')) {
            $keyColumn = 'key';
        } elseif (Schema::hasColumn('module_settings', 'name')) {
            $keyColumn = 'name';
        } else {
            return;
        }

        DB::table('module_settings')
            ->where('module_name', 'Titan Core')
            ->where($keyColumn, 'status')
            ->delete();
    }
};
