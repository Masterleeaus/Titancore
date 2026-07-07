<?php

namespace Modules\TitanCore\Services;

use Illuminate\Support\Facades\DB;

/**
 * Resolves KB collections with tenant override support.
 * Pass 3 integration: read from `ai_kb_collections` when present, otherwise fall back to config defaults.
 */
class KbCollectionService
{
    public function resolve(string $keySlug, ?int $tenantId = null): array
    {
        try {
            if ($this->tableExists('ai_kb_collections')) {
                if ($tenantId !== null) {
                    $row = DB::table('ai_kb_collections')->where('key_slug', $keySlug)->where('tenant_id', $tenantId)->first();
                    if ($row) {
                        return (array) $row;
                    }
                }
                $row = DB::table('ai_kb_collections')->where('key_slug', $keySlug)->whereNull('tenant_id')->first();
                if ($row) {
                    return (array) $row;
                }
            }
        } catch (\Throwable $e) {
            // fall through
        }

        $defaults = config('titan_agents.default_collections', []);
        $meta = $defaults[$keySlug] ?? null;

        return [
            'key_slug' => $keySlug,
            'title' => $meta['title'] ?? ucfirst(str_replace('_', ' ', $keySlug)),
            'scope_type' => $meta['scope_type'] ?? 'general',
            'agent_slug' => $meta['agent_slug'] ?? null,
            'tenant_id' => $tenantId,
        ];
    }

    private function tableExists(string $table): bool
    {
        return DB::getSchemaBuilder()->hasTable($table);
    }

    /**
     * Pass 5: Ensure default KB collections exist (DB upsert), used by settings + jobs.
     */
    public function syncDefaults(?int $tenantId = null): void
    {
        $defs = config('titancore.titan_agents.collections', []);
        foreach ($defs as $key => $c) {
            $row = \DB::table('ai_kb_collections')->where('key_slug',$key)->where('tenant_id',$tenantId)->first();
            if ($row) {
                \DB::table('ai_kb_collections')->where('id',$row->id)->update([
                    'title' => $c['title'] ?? $row->title,
                    'scope_type' => $c['scope_type'] ?? ($row->scope_type ?? 'general'),
                    'agent_slug' => $c['agent_slug'] ?? $row->agent_slug,
                    'updated_at' => now(),
                ]);
            } else {
                \DB::table('ai_kb_collections')->insert([
                    'tenant_id' => $tenantId,
                    'key_slug' => $key,
                    'title' => $c['title'] ?? $key,
                    'scope_type' => $c['scope_type'] ?? 'general',
                    'agent_slug' => $c['agent_slug'] ?? null,
                    'meta' => json_encode([]),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

}
