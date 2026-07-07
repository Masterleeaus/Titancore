<?php
namespace Modules\TitanCore\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\TitanCore\Services\TitanDocsKnowledgeSyncService;
use Modules\TitanCore\Services\TitanAIRunLogService;

/**
 * Pass 5: Settings-triggered KB ingest (non-blocking).
 */
class SyncTitanDocsKnowledgeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public ?int $tenantId = null,
        public bool $embed = false,
        public bool $includeDrafts = false,
        public int $limit = 0
    ) {}

    public function handle(TitanDocsKnowledgeSyncService $sync, TitanAIRunLogService $log): void
    {
        $runId = $log->create('sync_titandocs', $this->tenantId, $this->embed, ['limit'=>$this->limit,'include_drafts'=>$this->includeDrafts]);
        $log->start($runId);
        try {
            $res = $sync->sync($this->tenantId, $this->embed, $this->includeDrafts, $this->limit);
            if (($res['ok'] ?? false) === true) {
                $log->success($runId, ['documents'=>$res['documents'] ?? 0, 'chunks'=>$res['chunks'] ?? 0], 'AI knowledge base synced.');
            } else {
                $log->failed($runId, (string)($res['reason'] ?? 'sync failed'), $res);
            }
        } catch (\Throwable $e) {
            $log->failed($runId, $e->getMessage());
            throw $e;
        }
    }
}
