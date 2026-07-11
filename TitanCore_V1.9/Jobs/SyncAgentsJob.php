<?php
namespace Modules\TitanCore\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\TitanCore\Services\AgentRegistryService;
use Modules\TitanCore\Services\KbCollectionService;
use Modules\TitanCore\Services\TitanAIRunLogService;

/**
 * Pass 5: Settings-triggered sync (non-blocking).
 * This wraps the same logic as titan:sync-agents but runs via queue.
 */
class SyncAgentsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public ?int $tenantId = null) {}

    public function handle(AgentRegistryService $agents, KbCollectionService $kb, TitanAIRunLogService $log): void
    {
        $runId = $log->create('sync_agents', $this->tenantId, false);
        $log->start($runId);
        // "Sync" here means: ensure default agents/collections exist.
        try {
            $kb->syncDefaults($this->tenantId);
            $agents->syncDefaults($this->tenantId);
            $log->success($runId, ['documents'=>0,'chunks'=>0], 'Agent configuration synced.');
        } catch (\Throwable $e) {
            $log->failed($runId, $e->getMessage());
            throw $e;
        }
    }
}
