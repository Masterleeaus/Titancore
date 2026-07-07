<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('titan_ai_settings')) {
            Schema::create('titan_ai_settings', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('tenant_id')->nullable()->index();
                $table->string('key', 191)->index();
                $table->longText('value')->nullable();
                $table->timestamps();

                $table->unique(['tenant_id','key']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('titan_ai_settings');
    }
};
