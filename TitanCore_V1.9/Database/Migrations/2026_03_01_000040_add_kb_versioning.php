<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Add active_version_id to collections
        if (Schema::hasTable('ai_kb_collections') && !Schema::hasColumn('ai_kb_collections','active_version_id')) {
            Schema::table('ai_kb_collections', function (Blueprint $table) {
                if (!Schema::hasColumn('ai_kb_collections', 'active_version_id')) {
                    $table->unsignedBigInteger('active_version_id')->nullable()->after('agent_slug')->index();
                }
            });
        }

        if (!Schema::hasTable('titan_ai_kb_versions')) {
            Schema::create('titan_ai_kb_versions', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('tenant_id')->nullable()->index();
                $table->unsignedBigInteger('collection_id')->index();
                $table->string('label', 191)->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

                $table->index(['tenant_id','collection_id']);
            });
        }

        if (!Schema::hasTable('titan_ai_kb_chunk_snapshots')) {
            Schema::create('titan_ai_kb_chunk_snapshots', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('version_id')->index();
                $table->unsignedBigInteger('document_id')->index();
                $table->unsignedInteger('chunk_index')->default(0);
                $table->longText('content');
                $table->longText('embedding')->nullable();
                $table->longText('meta')->nullable();
                $table->timestamps();

                $table->index(['version_id','document_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('titan_ai_kb_chunk_snapshots');
        Schema::dropIfExists('titan_ai_kb_versions');

        if (Schema::hasTable('ai_kb_collections') && Schema::hasColumn('ai_kb_collections','active_version_id')) {
            Schema::table('ai_kb_collections', function (Blueprint $table) {
                $table->dropColumn('active_version_id');
            });
        }
    }
};
