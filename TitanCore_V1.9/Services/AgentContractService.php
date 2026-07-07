<?php
namespace Modules\TitanCore\Services;

use Illuminate\Support\Facades\DB;

class AgentContractService
{
    public function publishFromCurrent(string $agentSlug, ?int $tenantId = null, ?int $userId = null): array
    {
        $payload = $this->currentAgentPayload($agentSlug, $tenantId);

        $hash = hash('sha256', json_encode($payload));
        $version = 1;

        if (DB::getSchemaBuilder()->hasTable('titan_ai_agent_contracts')) {
            $max = DB::table('titan_ai_agent_contracts')
                ->where('agent_slug',$agentSlug)
                ->where('tenant_id',$tenantId)
                ->max('version');
            $version = ((int)$max) + 1;
        }

        return DB::transaction(function () use ($tenantId, $agentSlug, $version, $hash, $payload, $userId) {
            $id = (int) DB::table('titan_ai_agent_contracts')->insertGetId([
                'tenant_id' => $tenantId,
                'agent_slug' => $agentSlug,
                'version' => $version,
                'hash' => $hash,
                'payload' => json_encode($payload),
                'created_by' => $userId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // upsert active
            $row = DB::table('titan_ai_agent_active_contracts')
                ->where('tenant_id',$tenantId)->where('agent_slug',$agentSlug)->first();

            if ($row) {
                DB::table('titan_ai_agent_active_contracts')->where('id',$row->id)->update([
                    'contract_id' => $id,
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('titan_ai_agent_active_contracts')->insert([
                    'tenant_id' => $tenantId,
                    'agent_slug' => $agentSlug,
                    'contract_id' => $id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            return ['ok'=>true,'contract_id'=>$id,'version'=>$version,'hash'=>$hash];
        });
    }

    public function activePayload(string $agentSlug, ?int $tenantId = null): ?array
    {
        if (!DB::getSchemaBuilder()->hasTable('titan_ai_agent_active_contracts')) return null;

        $row = DB::table('titan_ai_agent_active_contracts')->where('tenant_id',$tenantId)->where('agent_slug',$agentSlug)->first();
        if (!$row) return null;

        $c = DB::table('titan_ai_agent_contracts')->where('id',$row->contract_id)->first();
        if (!$c) return null;

        return json_decode($c->payload ?? '{}', true);
    }

    public function activeInfo(string $agentSlug, ?int $tenantId = null): ?array
    {
        if (!DB::getSchemaBuilder()->hasTable('titan_ai_agent_active_contracts')) return null;
        $row = DB::table('titan_ai_agent_active_contracts')->where('tenant_id',$tenantId)->where('agent_slug',$agentSlug)->first();
        if (!$row) return null;
        $c = DB::table('titan_ai_agent_contracts')->where('id',$row->contract_id)->first();
        if (!$c) return null;

        return [
            'contract_id' => (int)$c->id,
            'version' => (int)$c->version,
            'hash' => (string)$c->hash,
            'created_at' => (string)$c->created_at,
        ];
    }

    /**
     * Build the "contract payload" from the current agent definition sources.
     * IMPORTANT: this bypasses contracts.
     */
    public function currentAgentPayload(string $agentSlug, ?int $tenantId = null): array
    {
        // DB first
        try {
            if (DB::getSchemaBuilder()->hasTable('ai_agents')) {
                if ($tenantId !== null) {
                    $row = DB::table('ai_agents')->where('slug',$agentSlug)->where('tenant_id',$tenantId)->first();
                    if ($row) return $this->normalizeRow((array)$row);
                }
                $row = DB::table('ai_agents')->where('slug',$agentSlug)->whereNull('tenant_id')->first();
                if ($row) return $this->normalizeRow((array)$row);
            }
        } catch (\Throwable $e) {}

        // config fallback
        $defs = config('titancore.titan_agents.agents', []);
        $a = $defs[$agentSlug] ?? null;
        if (!$a) {
            return [
                'slug' => $agentSlug,
                'title' => ucfirst(str_replace('_',' ',$agentSlug)),
                'kb_collection_key' => config('titan_agents.general_collection_key', 'kb_general_cleaning'),
                'is_active' => 1,
                'meta' => [],
            ];
        }

        return [
            'slug' => $agentSlug,
            'title' => $a['title'] ?? $agentSlug,
            'kb_collection_key' => $a['kb_collection_key'] ?? 'kb_general_cleaning',
            'is_active' => (int)($a['is_active'] ?? 1),
            'meta' => $a['meta'] ?? [],
        ];
    }

    private function normalizeRow(array $row): array
    {
        $meta = $row['meta'] ?? null;
        if (is_string($meta)) {
            $decoded = json_decode($meta, true);
            $meta = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($meta)) $meta = [];

        return [
            'slug' => (string)($row['slug'] ?? ''),
            'title' => (string)($row['title'] ?? ''),
            'kb_collection_key' => (string)($row['kb_collection_key'] ?? 'kb_general_cleaning'),
            'description' => $row['description'] ?? null,
            'is_active' => (int)($row['is_active'] ?? 1),
            'meta' => $meta,
        ];
    }
}
