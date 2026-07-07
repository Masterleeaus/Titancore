<?php

namespace Modules\TitanCore\Services\ModulePersistence;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TitanModuleManifestSnapshotStore
{
    /**
     * Persist a new manifest snapshot if the content hash changed.
     */
    public function sync(string $moduleName, array $payload): bool
    {
        if (! Schema::hasTable('titan_module_manifest_snapshots')) {
            return false;
        }

        $encoded = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $hash = hash('sha256', $encoded ?: '{}');

        $latestHash = DB::table('titan_module_manifest_snapshots')
            ->where('module_name', $moduleName)
            ->latest('id')
            ->value('manifest_hash');

        if ($latestHash === $hash) {
            return false;
        }

        DB::table('titan_module_manifest_snapshots')->insert([
            'module_name' => $moduleName,
            'manifest_hash' => $hash,
            'payload' => $encoded ?: '{}',
            'synced_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return true;
    }
}
