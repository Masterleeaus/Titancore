<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('ai_usage')) {
            Schema::create('ai_usage', function (Blueprint $table) {
                $table->id();
                $table->string('key', 64)->index(); // tenant:ID or global
                $table->date('date')->index();
                $table->unsignedBigInteger('requests')->default(0);
                $table->unsignedBigInteger('tokens')->default(0);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('ai_usage')) {
            Schema::dropIfExists('ai_usage');
        }
    }
};
