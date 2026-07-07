<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('ai_agents') && !Schema::hasColumn('ai_agents', 'company_id')) {
            Schema::table('ai_agents', function (Blueprint $table) {
                $table->unsignedBigInteger('company_id')->nullable()->index();
            });
        }
        if (Schema::hasTable('ai_artifacts') && !Schema::hasColumn('ai_artifacts', 'company_id')) {
            Schema::table('ai_artifacts', function (Blueprint $table) {
                $table->unsignedBigInteger('company_id')->nullable()->index();
            });
        }
        if (Schema::hasTable('ai_kb_chunks') && !Schema::hasColumn('ai_kb_chunks', 'company_id')) {
            Schema::table('ai_kb_chunks', function (Blueprint $table) {
                $table->unsignedBigInteger('company_id')->nullable()->index();
            });
        }
        if (Schema::hasTable('ai_kb_collection_docs') && !Schema::hasColumn('ai_kb_collection_docs', 'company_id')) {
            Schema::table('ai_kb_collection_docs', function (Blueprint $table) {
                $table->unsignedBigInteger('company_id')->nullable()->index();
            });
        }
        if (Schema::hasTable('ai_kb_collections') && !Schema::hasColumn('ai_kb_collections', 'company_id')) {
            Schema::table('ai_kb_collections', function (Blueprint $table) {
                $table->unsignedBigInteger('company_id')->nullable()->index();
            });
        }
        if (Schema::hasTable('ai_kb_documents') && !Schema::hasColumn('ai_kb_documents', 'company_id')) {
            Schema::table('ai_kb_documents', function (Blueprint $table) {
                $table->unsignedBigInteger('company_id')->nullable()->index();
            });
        }
        if (Schema::hasTable('ai_kb_sources') && !Schema::hasColumn('ai_kb_sources', 'company_id')) {
            Schema::table('ai_kb_sources', function (Blueprint $table) {
                $table->unsignedBigInteger('company_id')->nullable()->index();
            });
        }
        if (Schema::hasTable('ai_prompts') && !Schema::hasColumn('ai_prompts', 'company_id')) {
            Schema::table('ai_prompts', function (Blueprint $table) {
                $table->unsignedBigInteger('company_id')->nullable()->index();
            });
        }
        if (Schema::hasTable('ai_runs') && !Schema::hasColumn('ai_runs', 'company_id')) {
            Schema::table('ai_runs', function (Blueprint $table) {
                $table->unsignedBigInteger('company_id')->nullable()->index();
            });
        }
        if (Schema::hasTable('ai_usage') && !Schema::hasColumn('ai_usage', 'company_id')) {
            Schema::table('ai_usage', function (Blueprint $table) {
                $table->unsignedBigInteger('company_id')->nullable()->index();
            });
        }
        if (Schema::hasTable('ai_usage_ledger') && !Schema::hasColumn('ai_usage_ledger', 'company_id')) {
            Schema::table('ai_usage_ledger', function (Blueprint $table) {
                $table->unsignedBigInteger('company_id')->nullable()->index();
            });
        }
        if (Schema::hasTable('titan_ai_agent_active_contracts') && !Schema::hasColumn('titan_ai_agent_active_contracts', 'company_id')) {
            Schema::table('titan_ai_agent_active_contracts', function (Blueprint $table) {
                $table->unsignedBigInteger('company_id')->nullable()->index();
            });
        }
        if (Schema::hasTable('titan_ai_agent_contracts') && !Schema::hasColumn('titan_ai_agent_contracts', 'company_id')) {
            Schema::table('titan_ai_agent_contracts', function (Blueprint $table) {
                $table->unsignedBigInteger('company_id')->nullable()->index();
            });
        }
        if (Schema::hasTable('titan_ai_kb_chunk_snapshots') && !Schema::hasColumn('titan_ai_kb_chunk_snapshots', 'company_id')) {
            Schema::table('titan_ai_kb_chunk_snapshots', function (Blueprint $table) {
                $table->unsignedBigInteger('company_id')->nullable()->index();
            });
        }
        if (Schema::hasTable('titan_ai_kb_versions') && !Schema::hasColumn('titan_ai_kb_versions', 'company_id')) {
            Schema::table('titan_ai_kb_versions', function (Blueprint $table) {
                $table->unsignedBigInteger('company_id')->nullable()->index();
            });
        }
        if (Schema::hasTable('titan_ai_runs') && !Schema::hasColumn('titan_ai_runs', 'company_id')) {
            Schema::table('titan_ai_runs', function (Blueprint $table) {
                $table->unsignedBigInteger('company_id')->nullable()->index();
            });
        }
        if (Schema::hasTable('titan_ai_settings') && !Schema::hasColumn('titan_ai_settings', 'company_id')) {
            Schema::table('titan_ai_settings', function (Blueprint $table) {
                $table->unsignedBigInteger('company_id')->nullable()->index();
            });
        }
        if (Schema::hasTable('titan_ai_usage') && !Schema::hasColumn('titan_ai_usage', 'company_id')) {
            Schema::table('titan_ai_usage', function (Blueprint $table) {
                $table->unsignedBigInteger('company_id')->nullable()->index();
            });
        }
        if (Schema::hasTable('titan_tenant_links') && !Schema::hasColumn('titan_tenant_links', 'company_id')) {
            Schema::table('titan_tenant_links', function (Blueprint $table) {
                $table->unsignedBigInteger('company_id')->nullable()->index();
            });
        }
        if (Schema::hasTable('titan_tools') && !Schema::hasColumn('titan_tools', 'company_id')) {
            Schema::table('titan_tools', function (Blueprint $table) {
                $table->unsignedBigInteger('company_id')->nullable()->index();
            });
        }
        if (Schema::hasTable('titancore_settings') && !Schema::hasColumn('titancore_settings', 'company_id')) {
            Schema::table('titancore_settings', function (Blueprint $table) {
                $table->unsignedBigInteger('company_id')->nullable()->index();
            });
        }
    }

    public function down(): void
    {
        // intentionally non-destructive
    }
};
