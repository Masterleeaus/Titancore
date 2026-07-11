<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('permissions')) return;

        $permCols = Schema::getColumnListing('permissions');
        if (!in_array('name', $permCols)) return;

        $moduleId = $this->resolveModuleId();
        $hasModuleId = in_array('module_id', $permCols);
        $hasAllowed = in_array('allowed_permissions', $permCols);

        $map = @include __DIR__ . '/../../Config/permissions.php';
        if (!is_array($map)) $map = [];

        foreach ($map as $key => $label) {
            $row = ['name' => $key];

            if (in_array('display_name', $permCols)) $row['display_name'] = $label;
            if (in_array('description', $permCols)) $row['description'] = $label;
            if ($hasAllowed) $row['allowed_permissions'] = '["all"]';
            if ($hasModuleId && $moduleId) $row['module_id'] = $moduleId;
            if (in_array('is_custom', $permCols)) $row['is_custom'] = 0;
            if (in_array('created_at', $permCols)) $row['created_at'] = now();
            if (in_array('updated_at', $permCols)) $row['updated_at'] = now();

            $q = DB::table('permissions')->where('name', $key);
            if ($hasModuleId && isset($row['module_id'])) $q->where('module_id', $row['module_id']);
            $existing = $q->first();

            if ($existing) {
                $update = $row;
                unset($update['name']);
                if ($hasModuleId) unset($update['module_id']);
                DB::table('permissions')->where('id', $existing->id)->update($update);
            } else {
                DB::table('permissions')->updateOrInsert(['name' => $row['name']], $row);
            }
        }
    }

    private function resolveModuleId(): ?int
    {
        if (!Schema::hasTable('modules')) return null;

        $cols = Schema::getColumnListing('modules');
        if (!in_array('id', $cols)) return null;

        $searchCols = [];
        foreach (['module_name','name','alias','slug','system_name'] as $c) {
            if (in_array($c, $cols)) $searchCols[] = $c;
        }
        if (!$searchCols) return null;

        $q = DB::table('modules');
        foreach ($searchCols as $c) {
            $q->orWhere($c, 'TitanCore')
              ->orWhere($c, 'titancore')
              ->orWhere($c, 'Titan Core');
        }
        $row = $q->first();
        return $row ? (int)$row->id : null;
    }

    public function down(): void
    {
        // non-destructive
    }
};
