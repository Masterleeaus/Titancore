<?php
namespace Modules\TitanCore\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\TitanCore\Services\AgentContractService;
use Modules\TitanCore\Services\TitanAIRunLogService;

class PublishAgentContractJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $agentSlug,
        public ?int $tenantId = null,
        public ?int $userId = null
    ) {}

    public function handle(AgentContractService $contracts, TitanAIRunLogService $log): void
    {
        $runId = $log->create('publish_agent', $this->tenantId, false, ['agent'=>$this->agentSlug]);
        $log->start($runId);

        try {
            $res = $contracts->publishFromCurrent($this->agentSlug, $this->tenantId, $this->userId);
            if (($res['ok'] ?? false) === true) {
                $log->success($runId, ['documents'=>0,'chunks'=>0], 'Agent contract published.');
            } else {
                $log->failed($runId, (string)($res['reason'] ?? 'publish failed'), $res);
            }
        } catch (\Throwable $e) {
            $log->failed($runId, $e->getMessage());
            throw $e;
        }
    }
}
