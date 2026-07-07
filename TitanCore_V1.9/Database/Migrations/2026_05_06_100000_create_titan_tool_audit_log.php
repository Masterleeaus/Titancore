<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('titan_tool_audit_log')) {
            return;
        }

        Schema::create('titan_tool_audit_log', function (Blueprint $table) {
            $table->id();

            // Tool identification
            $table->string('tool', 120)->index();

            // Actor context
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->unsignedBigInteger('company_id')->nullable()->index();

            // Input fingerprint (SHA-256 of JSON-encoded params — not the raw input)
            $table->string('input_hash', 64)->nullable();

            // Execution outcome: success | failed | timed_out | blocked | dry_run
            $table->string('status', 32)->default('success')->index();

            // Wall-clock time of the handler invocation in milliseconds
            $table->unsignedInteger('duration_ms')->nullable();

            // Error or blocking reason (populated on non-success statuses)
            $table->text('error_message')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('titan_tool_audit_log');
    }
};
