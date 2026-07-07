<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Seed 'titan_core' as a subscription package feature for all existing packages.
     * Safe to run even if BusinessSettingsModule tables do not yet exist.
     */
    public function up(): void
    {
        if (!Schema::hasTable('subscription_packages') || !Schema::hasTable('subscription_package_features')) {
            return;
        }

        $packages = DB::table('subscription_packages')->pluck('id');

        foreach ($packages as $packageId) {
            $alreadyExists = DB::table('subscription_package_features')
                ->where('subscription_package_id', $packageId)
                ->where('feature', 'titan_core')
                ->exists();

            if (!$alreadyExists) {
                DB::table('subscription_package_features')->insert([
                    'id'                      => (string) Str::uuid(),
                    'subscription_package_id' => $packageId,
                    'feature'                 => 'titan_core',
                    'company_id'              => null,
                    'created_at'              => now(),
                    'updated_at'              => now(),
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('subscription_package_features')) {
            return;
        }

        DB::table('subscription_package_features')
            ->where('feature', 'titan_core')
            ->delete();
    }
};
