<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('titan_ai_agent_contracts')) {
            Schema::create('titan_ai_agent_contracts', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('tenant_id')->nullable()->index();
                $table->string('agent_slug', 120)->index();
                $table->unsignedInteger('version')->default(1)->index();
                $table->string('hash', 128)->index();
                $table->longText('payload'); // json
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

                $table->index(['tenant_id','agent_slug','version']);
            });
        }

        if (!Schema::hasTable('titan_ai_agent_active_contracts')) {
            Schema::create('titan_ai_agent_active_contracts', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('tenant_id')->nullable()->index();
                $table->string('agent_slug', 120)->index();
                $table->unsignedBigInteger('contract_id')->index();
                $table->timestamps();

                $table->unique(['tenant_id','agent_slug']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('titan_ai_agent_active_contracts');
        Schema::dropIfExists('titan_ai_agent_contracts');
    }
};
