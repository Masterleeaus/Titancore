<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('titan_module_vectors')) {
            return;
        }

        Schema::create('titan_module_vectors', function (Blueprint $table) {
            $table->bigIncrements('id');
            // Stable caller-supplied identifier; must be unique per document.
            $table->string('external_id', 255);
            $table->unsignedBigInteger('company_id')->nullable()->index();
            $table->string('module', 120)->nullable()->index();
            $table->longText('content');
            // JSON-encoded float array — used by all non-pgvector drivers and
            // as the authoritative store for pgvector (the native vector column
            // is added below via raw SQL for Postgres only).
            $table->longText('embedding')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique('external_id');
            $table->index(['company_id', 'module']);
        });

        // Postgres + pgvector: add a native vector column and an IVFFlat index.
        // The extension and raw DDL are applied inside a try/catch so that the
        // migration succeeds on MySQL / SQLite environments where pgvector is
        // not available; those environments use the PHP cosine fallback path.
        if (DB::getDriverName() === 'pgsql') {
            try {
                DB::statement('CREATE EXTENSION IF NOT EXISTS vector');

                $dim = (int) config('titan-ai.vector_store.pgvector.dimensions', 1536);
                DB::statement(
                    "ALTER TABLE titan_module_vectors ADD COLUMN IF NOT EXISTS embedding_vector vector({$dim})"
                );

                DB::statement(
                    <<<SQL
                    CREATE INDEX IF NOT EXISTS tmv_embedding_vector_cosine_idx
                    ON titan_module_vectors
                    USING ivfflat (embedding_vector vector_cosine_ops)
                    WITH (lists = 100)
                    SQL
                );
            } catch (\Throwable $e) {
                // pgvector extension not installed — continue with JSON fallback.
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('titan_module_vectors');
    }
};
