<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('titan_modules')) {
            Schema::create('titan_modules', function (Blueprint $table) {
                $table->id();
                $table->string('name')->unique();
                $table->string('version')->nullable();
                $table->string('status', 32)->default('enabled');
                $table->timestamp('installed_at')->nullable();
                $table->timestamp('enabled_at')->nullable();
                $table->timestamp('disabled_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('titan_module_manifest_snapshots')) {
            Schema::create('titan_module_manifest_snapshots', function (Blueprint $table) {
                $table->id();
                $table->string('module_name')->index();
                $table->string('manifest_hash', 64);
                $table->json('payload');
                $table->timestamp('synced_at');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('titan_module_upgrade_tracking')) {
            Schema::create('titan_module_upgrade_tracking', function (Blueprint $table) {
                $table->id();
                $table->string('module_name')->index();
                $table->string('upgrade_file');
                $table->string('status', 32);
                $table->text('error_message')->nullable();
                $table->timestamp('ran_at')->nullable();
                $table->timestamps();

                $table->unique(['module_name', 'upgrade_file']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('titan_module_upgrade_tracking');
        Schema::dropIfExists('titan_module_manifest_snapshots');
        Schema::dropIfExists('titan_modules');
    }
};
