<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        Schema::table('permissions', function (Blueprint $table) {
            if (! Schema::hasColumn('permissions', 'guard_name')) {
                $table->string('guard_name')->default('web')->after('name');
            }
            if (! Schema::hasColumn('permissions', 'display_name')) {
                $table->string('display_name')->nullable()->after('name');
            }
            if (! Schema::hasColumn('permissions', 'is_custom')) {
                $table->boolean('is_custom')->default(false)->after('display_name');
            }
            if (! Schema::hasColumn('permissions', 'module_id')) {
                $table->unsignedBigInteger('module_id')->nullable()->after('is_custom');
            }
            if (! Schema::hasColumn('permissions', 'allowed_permissions')) {
                $table->unsignedTinyInteger('allowed_permissions')->nullable()->after('module_id');
            }
        });
    }

    public function down(): void
    {
        // Compatibility columns are intentionally retained for legacy module seeders.
    }
};
