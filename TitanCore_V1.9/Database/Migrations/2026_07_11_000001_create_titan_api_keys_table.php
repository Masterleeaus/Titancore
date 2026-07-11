<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('titan_api_keys')) {
            return;
        }

        Schema::create('titan_api_keys', function (Blueprint $table) {
            $table->id();

            // Key identification
            $table->string('name', 120)->index();
            $table->string('key_prefix', 12)->index();   // First 12 chars — safe to display
            $table->string('key_hash', 64)->unique();    // SHA-256 of the full key

            // Actor context
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->unsignedBigInteger('company_id')->nullable()->index();

            // Scope and permissions
            $table->json('scopes')->nullable();          // e.g. ["platform:read", "platform:write"]
            $table->string('description', 500)->nullable();

            // Lifecycle
            $table->boolean('active')->default(true)->index();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('titan_api_keys');
    }
};
