<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('titan_ai_runs')) {
            Schema::create('titan_ai_runs', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('tenant_id')->nullable()->index();
                $table->string('run_type', 80)->index(); // sync_agents | sync_titandocs | embed_rebuild
                $table->string('status', 40)->default('queued')->index(); // queued|running|success|failed
                $table->unsignedInteger('documents')->default(0);
                $table->unsignedInteger('chunks')->default(0);
                $table->boolean('embed')->default(false);
                $table->text('message')->nullable();
                $table->longText('meta')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('finished_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('titan_ai_runs');
    }
};
