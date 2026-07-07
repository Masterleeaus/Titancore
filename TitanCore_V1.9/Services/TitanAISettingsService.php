<?php
namespace Modules\TitanCore\Services;

use Illuminate\Support\Facades\DB;

class TitanAISettingsService
{
    public function get(string $key, ?int $tenantId = null, mixed $default = null): mixed
    {
        if (!DB::getSchemaBuilder()->hasTable('titan_ai_settings')) return $default;

        $row = DB::table('titan_ai_settings')->where('key', $key)->where('tenant_id', $tenantId)->first();
        if (!$row && $tenantId !== null) {
            $row = DB::table('titan_ai_settings')->where('key', $key)->whereNull('tenant_id')->first();
        }
        if (!$row) return $default;

        $val = $row->value;
        $decoded = json_decode($val, true);
        return json_last_error() === JSON_ERROR_NONE ? $decoded : $val;
    }

    public function set(string $key, mixed $value, ?int $tenantId = null): void
    {
        if (!DB::getSchemaBuilder()->hasTable('titan_ai_settings')) return;

        DB::table('titan_ai_settings')->updateOrInsert(
            ['tenant_id' => $tenantId, 'key' => $key],
            ['value' => is_string($value) ? $value : json_encode($value), 'updated_at' => now(), 'created_at' => now()]
        );
    }
}
