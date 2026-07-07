<?php
namespace Modules\TitanCore\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\TitanCore\Services\EmbeddingService;
use Modules\TitanCore\Services\TitanAIRunLogService;
use Illuminate\Support\Facades\DB;

class ReEmbedMissingChunksJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public ?int $tenantId = null, public int $limit = 500) {}

    public function handle(EmbeddingService $embeddings, TitanAIRunLogService $log): void
    {
        $runId = $log->create('embed_missing', $this->tenantId, true, ['limit'=>$this->limit]);
        $log->start($runId);

        try {
            $rows = DB::table('ai_kb_chunks')->whereNull('embedding')->limit($this->limit)->get();
            $count = 0;

            foreach ($rows as $r) {
                $e = $embeddings->embedText($r->content);
                if (!isset($e['error']) && !empty($e['vector'])) {
                    DB::table('ai_kb_chunks')->where('id',$r->id)->update([
                        'embedding' => json_encode($e['vector']),
                        'updated_at' => now(),
                    ]);
                    $count++;
                }
            }

            $log->success($runId, ['documents'=>0,'chunks'=>$count], 'Missing embeddings generated.');
        } catch (\Throwable $e) {
            $log->failed($runId, $e->getMessage());
            throw $e;
        }
    }
}
