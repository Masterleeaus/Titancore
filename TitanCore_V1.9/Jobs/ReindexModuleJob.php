<?php

namespace Modules\TitanCore\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Modules\TitanCore\Contracts\AI\VectorStoreContract;
use Modules\TitanCore\Services\TitanAIRunLogService;

/**
 * ReindexModuleJob
 *
 * Bulk-indexes all AI knowledge-base chunks belonging to a specific module
 * (and optionally a single tenant) into the configured vector store backend.
 * Dispatches asynchronously so large corpora are processed without blocking
 * web requests.
 *
 * Usage:
 *   ReindexModuleJob::dispatch('CleaningJobs');
 *   ReindexModuleJob::dispatch('CleaningJobs', companyId: 42, chunkLimit: 1000);
 */
class ReindexModuleJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Number of retry attempts before the job is failed. */
    public int $tries = 3;

    /** Seconds before the job times out. */
    public int $timeout = 300;

    public function __construct(
        public readonly string $module,
        public readonly ?int   $companyId = null,
        public readonly int    $chunkLimit = 500,
    ) {}

    public function handle(VectorStoreContract $store, TitanAIRunLogService $log): void
    {
        $runId = $log->create('reindex_module', $this->companyId, true, [
            'module'      => $this->module,
            'chunk_limit' => $this->chunkLimit,
        ]);
        $log->start($runId);

        try {
            $indexed = 0;
            $failed  = 0;

            $this->fetchChunks()->each(function ($chunk) use ($store, &$indexed, &$failed) {
                $metadata = array_filter([
                    'module'     => $this->module,
                    'company_id' => $this->companyId,
                    'chunk_id'   => $chunk->id,
                    'document_id'=> $chunk->document_id ?? null,
                ]);

                $result = $store->index(
                    id:       "module:{$this->module}:chunk:{$chunk->id}",
                    content:  (string) $chunk->content,
                    metadata: $metadata,
                );

                $result['ok'] ? $indexed++ : $failed++;
            });

            $log->success($runId, [
                'module'  => $this->module,
                'indexed' => $indexed,
                'failed'  => $failed,
            ], "Reindex complete for module {$this->module}.");
        } catch (\Throwable $e) {
            $log->failed($runId, $e->getMessage());
            throw $e;
        }
    }

    /**
     * Load a bounded set of chunks for this module from the knowledge-base
     * chunks table.  Scopes to company_id when provided so multi-tenant
     * reindexing can be parallelised by dispatching one job per tenant.
     *
     * @return \Illuminate\Support\LazyCollection<int, object>
     */
    private function fetchChunks(): \Illuminate\Support\LazyCollection
    {
        $chunksQuery = DB::table('ai_kb_chunks as c')
            ->select([
                'c.id',
                'c.content',
                'c.document_id',
            ])
            ->join('ai_kb_documents as d', 'd.id', '=', 'c.document_id')
            ->whereNotNull('c.content')
            ->where('c.content', '!=', '')
            ->limit($this->chunkLimit);

        if ($this->companyId !== null) {
            // Chunks inherit company scoping via the parent collection; filter
            // through the collection → document relationship if the column exists.
            if (DB::getSchemaBuilder()->hasColumn('ai_kb_documents', 'company_id')) {
                $chunksQuery->where('d.company_id', $this->companyId);
            }
        }

        return $chunksQuery->lazyById(100, 'c.id', 'id');
    }
}
