<?php
namespace Modules\TitanCore\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\TitanCore\Services\KBPublishService;
use Modules\TitanCore\Services\TitanAIRunLogService;

class PublishKbSnapshotJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $collectionKey,
        public ?int $tenantId = null,
        public ?string $label = null,
        public ?int $userId = null
    ) {}

    public function handle(KBPublishService $pub, TitanAIRunLogService $log): void
    {
        $runId = $log->create('publish_kb', $this->tenantId, true, ['collection'=>$this->collectionKey,'label'=>$this->label]);
        $log->start($runId);

        try {
            $res = $pub->publishCollection($this->collectionKey, $this->tenantId, $this->label, $this->userId);
            if (($res['ok'] ?? false) === true) {
                $log->success($runId, ['documents'=>$res['documents'] ?? 0,'chunks'=>$res['chunks'] ?? 0], 'KB snapshot published.');
            } else {
                $log->failed($runId, (string)($res['reason'] ?? 'publish failed'), $res);
            }
        } catch (\Throwable $e) {
            $log->failed($runId, $e->getMessage());
            throw $e;
        }
    }
}
