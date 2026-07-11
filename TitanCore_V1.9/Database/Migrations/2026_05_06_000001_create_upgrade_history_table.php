<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('upgrade_history')) {
            return;
        }

        Schema::create('upgrade_history', function (Blueprint $table) {
            $table->id();
            $table->string('module_name')->index();
            $table->string('version')->default('unknown');
            $table->json('files_applied')->nullable();
            $table->string('snapshot_path', 500)->nullable();
            $table->string('status', 32)->default('success'); // success | failed | rolled_back
            $table->text('error_detail')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('upgrade_history');
    }
};
