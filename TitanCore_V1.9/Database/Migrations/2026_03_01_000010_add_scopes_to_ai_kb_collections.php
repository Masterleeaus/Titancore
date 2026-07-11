<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('ai_kb_collections')) {

        Schema::table('ai_kb_collections', function (Blueprint $t) {
            if (!Schema::hasColumn('ai_kb_collections', 'scope_type')) {
                $t->string('scope_type', 32)->default('general')->after('title');
            }
            if (!Schema::hasColumn('ai_kb_collections', 'agent_slug')) {
                $t->string('agent_slug', 128)->nullable()->after('scope_type');
            }
        });

        }

        // Make collection keys tenant-scoped (so each tenant can override/extend).
        // NOTE: the original migration used a global unique(key_slug).
        if (Schema::hasTable('ai_kb_collections')) {

        Schema::table('ai_kb_collections', function (Blueprint $t) {
            // MySQL typically auto-names the original unique index as: ai_kb_collections_key_slug_unique
            // If your DB differs, rename the index accordingly before running this migration.
            $t->dropUnique('ai_kb_collections_key_slug_unique');
            $t->unique(['tenant_id', 'key_slug'], 'ai_kb_collections_tenant_key_unique');
        });

        }
    }

    public function down(): void
    {
        if (Schema::hasTable('ai_kb_collections')) {

        Schema::table('ai_kb_collections', function (Blueprint $t) {
            $t->dropUnique('ai_kb_collections_tenant_key_unique');
            $t->unique('key_slug');
            $t->dropColumn(['scope_type', 'agent_slug']);
        });

        }
    }
};
