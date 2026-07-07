<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('ai_runs')) {
            Schema::create('ai_runs', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->string('module', 120)->nullable()->index();
                $table->string('action', 120)->nullable()->index();
                $table->string('provider', 50)->default('magicai')->index();
                $table->string('tool', 120)->nullable()->index();
                $table->longText('input')->nullable();
                $table->longText('output')->nullable();
                $table->integer('status_code')->nullable();
                $table->integer('tokens')->nullable();
                $table->decimal('cost', 10, 4)->nullable();
                $table->string('status', 30)->default('ok')->index();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('ai_artifacts')) {
            Schema::create('ai_artifacts', function (Blueprint $table) {
                $table->bigIncrements('id');
            $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->unsignedBigInteger('ai_run_id')->index();
                $table->string('provider', 50)->default('magicai');
                $table->string('artifact_type', 50)->nullable();
                $table->string('artifact_id', 191)->nullable();
                $table->string('url', 500)->nullable();
                $table->longText('meta')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('titan_tenant_links')) {
            Schema::create('titan_tenant_links', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('company_id')->unique();
                $table->string('provider', 50)->default('magicai');
                $table->string('workspace_id', 191)->nullable();
                $table->string('api_key', 191)->nullable(); // optional per-tenant key (encrypt at app layer)
                $table->string('status', 30)->default('active');
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('titan_tools')) {
            Schema::create('titan_tools', function (Blueprint $table) {
                $table->bigIncrements('id');
            $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->string('provider', 50)->default('magicai')->index();
                $table->string('tool_slug', 191)->index();
                $table->string('display_name', 191)->nullable();
                $table->string('risk', 30)->default('low')->index();
                $table->longText('input_schema')->nullable();
                $table->longText('output_schema')->nullable();
                $table->longText('meta')->nullable();
                $table->timestamps();
                $table->unique(['provider','tool_slug']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('titan_tools');
        Schema::dropIfExists('titan_tenant_links');
        Schema::dropIfExists('ai_artifacts');
        Schema::dropIfExists('ai_runs');
    }
};
