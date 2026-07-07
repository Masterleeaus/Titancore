<?php
namespace Modules\TitanCore\Console\Commands;

use Illuminate\Console\Command;
use Modules\TitanCore\Services\TitanDocsKnowledgeSyncService;

/**
 * Pass 5:
 * CLI wrapper for TitanDocsKnowledgeSyncService.
 */
class SyncTitanDocsKnowledgeCommand extends Command
{
    protected $signature = 'titan:kb:sync-titandocs {--tenant_id=} {--embed=1} {--limit=0}';
    protected $description = 'Sync TitanDocs templates/prompts into KB collections (general + agent-specific).';

    public function handle(TitanDocsKnowledgeSyncService $sync): int
    {
        $tenantId = $this->option('tenant_id');
        $tenantId = $tenantId !== null ? (int)$tenantId : null;

        $withEmbeddings = filter_var($this->option('embed'), FILTER_VALIDATE_BOOL);
        $limit = (int) ($this->option('limit') ?? 0);

        $result = $sync->sync($tenantId, $withEmbeddings, $limit);
        if (!($result['ok'] ?? false)) {
            $this->error($result['reason'] ?? 'Sync failed');
            return self::FAILURE;
        }

        $this->info("Done. Documents: {$result['documents']}, Chunks: {$result['chunks']}, Embed: ".($withEmbeddings?'yes':'no'));
        return self::SUCCESS;
    }
}
