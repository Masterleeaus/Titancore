<?php

namespace Modules\TitanCore\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SyncTitanAgentsCommand extends Command
{
    protected $signature = 'titan:sync-agents {--tenant_id=}';
    protected $description = 'Sync default TitanAgents + KB collections from TitanCore config into the database.';

    public function handle(): int
    {
        $tenantId = $this->option('tenant_id');
        $tenantId = ($tenantId === null || $tenantId === '') ? null : (int)$tenantId;

        if (!Schema::hasTable('ai_kb_collections') || !Schema::hasTable('ai_agents')) {
            $this->error('Required tables missing. Run migrations first.');
            return self::FAILURE;
        }

        $cfg = config('titan_agents', []);
        $collections = (array)($cfg['default_collections'] ?? []);
        $agents = (array)($cfg['default_agents'] ?? []);

        DB::beginTransaction();
        try {
            foreach ($collections as $keySlug => $def) {
                DB::table('ai_kb_collections')->updateOrInsert(
                    ['tenant_id' => $tenantId, 'key_slug' => (string)$keySlug],
                    [
                        'title' => (string)($def['title'] ?? $keySlug),
                        'scope_type' => (string)($def['scope_type'] ?? 'general'),
                        'agent_slug' => $def['agent_slug'] ?? null,
                        'meta' => isset($def['meta']) ? json_encode($def['meta']) : null,
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
            }

            foreach ($agents as $slug => $def) {
                DB::table('ai_agents')->updateOrInsert(
                    ['tenant_id' => $tenantId, 'slug' => (string)$slug],
                    [
                        'title' => (string)($def['title'] ?? $slug),
                        'description' => $def['description'] ?? null,
                        'kb_collection_key' => (string)($def['kb_collection_key'] ?? 'kb_general_cleaning'),
                        'meta' => isset($def['meta']) ? json_encode($def['meta']) : null,
                        'is_active' => (bool)($def['is_active'] ?? true),
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        $this->info('Synced KB collections + agents' . ($tenantId ? " for tenant_id={$tenantId}" : ' (global).'));
        return self::SUCCESS;
    }
}
