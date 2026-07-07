<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('titancore_settings')) {
        Schema::create('titancore_settings', function (Blueprint $table) {
            $table->id();
            $table->string('default_provider')->default('openai');
            $table->unsignedBigInteger('daily_token_limit')->default(200000);
            $table->boolean('auto_sync_kb')->default(true);
            $table->string('kb_collection_slug')->default('worksuite_core_kb');
            $table->timestamps();
        });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('titancore_settings');
    }
};
