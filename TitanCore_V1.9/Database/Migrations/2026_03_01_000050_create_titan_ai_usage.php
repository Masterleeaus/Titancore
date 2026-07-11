<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('titan_ai_usage')) {
            Schema::create('titan_ai_usage', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('tenant_id')->nullable()->index();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->unsignedBigInteger('run_id')->nullable()->index(); // titan_ai_runs.id
                $table->string('agent_slug', 120)->nullable()->index();

                $table->string('feature', 40)->index(); // chat|embed
                $table->string('provider', 60)->nullable()->index();
                $table->string('model', 120)->nullable()->index();

                $table->unsignedInteger('prompt_tokens')->default(0);
                $table->unsignedInteger('completion_tokens')->default(0);
                $table->unsignedInteger('total_tokens')->default(0);

                $table->decimal('cost_usd', 12, 6)->nullable();

                $table->longText('meta')->nullable(); // json
                $table->timestamps();

                $table->index(['tenant_id','feature']);
                $table->index(['tenant_id','agent_slug']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('titan_ai_usage');
    }
};
