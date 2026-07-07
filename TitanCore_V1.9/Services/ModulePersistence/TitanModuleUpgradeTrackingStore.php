<?php

namespace Modules\TitanCore\Services\ModulePersistence;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TitanModuleUpgradeTrackingStore
{
    public function hasSuccessfulRun(string $moduleName, string $upgradeFile): bool
    {
        if (! Schema::hasTable('titan_module_upgrade_tracking')) {
            return false;
        }

        return DB::table('titan_module_upgrade_tracking')
            ->where('module_name', $moduleName)
            ->where('upgrade_file', $upgradeFile)
            ->where('status', 'success')
            ->exists();
    }

    public function markSuccess(string $moduleName, string $upgradeFile): void
    {
        $this->persist($moduleName, $upgradeFile, 'success');
    }

    public function markFailed(string $moduleName, string $upgradeFile, string $errorMessage): void
    {
        $this->persist($moduleName, $upgradeFile, 'failed', $errorMessage);
    }

    private function persist(string $moduleName, string $upgradeFile, string $status, ?string $errorMessage = null): void
    {
        if (! Schema::hasTable('titan_module_upgrade_tracking')) {
            return;
        }

        $existing = DB::table('titan_module_upgrade_tracking')
            ->where('module_name', $moduleName)
            ->where('upgrade_file', $upgradeFile)
            ->first();

        $now = now();

        DB::table('titan_module_upgrade_tracking')->updateOrInsert(
            ['module_name' => $moduleName, 'upgrade_file' => $upgradeFile],
            [
                'status' => $status,
                'error_message' => $errorMessage,
                'ran_at' => $now,
                'updated_at' => $now,
                'created_at' => $existing?->created_at ?? $now,
            ]
        );
    }
}
