<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('ai_agents')) {
        Schema::create('ai_agents', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedBigInteger('tenant_id')->nullable();
            $t->string('slug', 128);
            $t->string('title');
            $t->text('description')->nullable();

            // The KB collection key the agent is allowed to use (topic-Configureed).
            $t->string('kb_collection_key', 128);

            // Agent runtime/meta: output schema, tool permissions, etc.
            $t->json('meta')->nullable();

            $t->boolean('is_active')->default(true);
            $t->timestamps();

            $t->unique(['tenant_id', 'slug'], 'ai_agents_tenant_slug_unique');
            $t->index(['tenant_id', 'is_active']);
        });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_agents');
    }
};
