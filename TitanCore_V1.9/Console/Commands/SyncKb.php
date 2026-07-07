<?php

namespace Modules\TitanCore\Console\Commands;

use Illuminate\Console\Command;
use Modules\TitanCore\Services\KnowledgeSyncService;

class SyncKb extends Command
{
    protected $signature = 'titancore:sync-kb';

    protected $description = 'Sync Worksuite core Knowledge Base into Titan Core KB';

    public function handle(KnowledgeSyncService $syncService): int
    {
        $this->info('Syncing Worksuite Knowledge Base into Titan Core...');
        $syncService->syncWorksuiteKnowledgeBase();
        $this->info('Done.');

        return static::SUCCESS;
    }
}
