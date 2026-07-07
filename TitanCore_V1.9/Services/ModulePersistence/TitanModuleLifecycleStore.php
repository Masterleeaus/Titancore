<?php

namespace Modules\TitanCore\Services\ModulePersistence;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TitanModuleLifecycleStore
{
    public function markEnabled(string $name, ?string $version): void
    {
        if (! Schema::hasTable('titan_modules')) {
            return;
        }

        $existing = DB::table('titan_modules')->where('name', $name)->first();
        $now = now();

        DB::table('titan_modules')->updateOrInsert(
            ['name' => $name],
            [
                'version' => $version,
                'status' => 'enabled',
                'installed_at' => $existing?->installed_at ?? $now,
                'enabled_at' => $now,
                'disabled_at' => null,
                'updated_at' => $now,
                'created_at' => $existing?->created_at ?? $now,
            ]
        );
    }

    public function markDisabled(string $name): void
    {
        if (! Schema::hasTable('titan_modules')) {
            return;
        }

        $existing = DB::table('titan_modules')->where('name', $name)->first();
        if (! $existing) {
            return;
        }

        $now = now();

        DB::table('titan_modules')->where('name', $name)->update([
            'status' => 'disabled',
            'disabled_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
