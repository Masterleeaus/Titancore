<?php

namespace Modules\TitanCore\AI\VectorStore;

use Illuminate\Support\Facades\DB;

/**
 * DatabaseVectorStore
 *
 * A database-backed vector store that accepts pre-computed embedding vectors
 * directly (no external embedding service required).  Uses the
 * titan_module_vectors table and performs cosine similarity in PHP, making it
 * suitable for test environments and low-volume production use cases.
 *
 * All operations are automatically scoped to the module name supplied at
 * construction time, preventing cross-module data leakage.
 *
 * When $companyId is provided every read and write is additionally scoped to
 * that tenant, enforcing the platform's company_id tenant boundary.  Pass null
 * only for system-level (non-tenant) operations where tenant isolation is not
 * required.
 *
 * Performance note: search() loads up to 200 candidate rows and ranks them in
 * PHP via cosine similarity.  For high-volume production workloads consider
 * using PgvectorStore (native vector ANN) or another backend.
 */
class DatabaseVectorStore
{
    public function __construct(
        protected string $module,
        protected ?int $companyId = null,
    ) {}

    /**
     * Persist a chunk with its pre-computed embedding vector.
     *
     * @param  string  $chunkId    Stable unique identifier for the chunk.
     * @param  string  $content    Plain-text content of the chunk.
     * @param  float[] $embedding  Pre-computed embedding vector.
     * @param  array   $metadata   Arbitrary metadata stored as JSON.
     * @return array{ok: bool, chunk_id: string}
     */
    public function store(string $chunkId, string $content, array $embedding, array $metadata = []): array
    {
        $row = [
            'external_id' => $chunkId,
            'module'      => $this->module,
            'company_id'  => $this->companyId,
            'content'     => $content,
            'embedding'   => json_encode(array_values(array_map('floatval', $embedding))),
            'metadata'    => json_encode($metadata),
            'updated_at'  => now(),
            'created_at'  => now(),
        ];

        DB::table('titan_module_vectors')->upsert(
            [$row],
            ['external_id'],
            ['content', 'embedding', 'metadata', 'updated_at'],
        );

        return ['ok' => true, 'chunk_id' => $chunkId];
    }

    /**
     * Find the top-K chunks most similar to the given query embedding.
     *
     * Similarity is measured by cosine similarity; results are returned in
     * descending order (most similar first).  The search is scoped to the
     * module and, when set, the company ID supplied at construction.
     *
     * @param  float[]  $queryEmbedding  Pre-computed query vector.
     * @param  int      $topK            Maximum number of results to return.
     * @return array<int, array{chunk_id: string, content: string, score: float, metadata: array}>
     */
    public function search(array $queryEmbedding, int $topK = 5): array
    {
        $query = DB::table('titan_module_vectors')
            ->whereNotNull('embedding')
            ->where('module', $this->module)
            ->select(['external_id', 'content', 'embedding', 'metadata'])
            ->orderByDesc('id')
            ->limit(200);

        if ($this->companyId !== null) {
            $query->where('company_id', $this->companyId);
        }

        $scored = [];

        foreach ($query->get() as $row) {
            $v = json_decode($row->embedding, true);

            if (! is_array($v) || empty($v)) {
                continue;
            }

            $scored[] = [
                'chunk_id' => $row->external_id,
                'content'  => $row->content,
                'score'    => $this->cosine($queryEmbedding, $v),
                'metadata' => json_decode($row->metadata ?? '{}', true) ?? [],
            ];
        }

        usort($scored, fn ($a, $b) => $b['score'] <=> $a['score']);

        return array_slice($scored, 0, $topK);
    }

    /**
     * Remove a previously stored chunk by its identifier.
     *
     * The delete is scoped to the module (and company when set) to prevent
     * cross-tenant deletion of vectors belonging to a different tenant.
     *
     * @param  string  $chunkId  The identifier used when calling store().
     * @return array{ok: bool}
     */
    public function delete(string $chunkId): array
    {
        $query = DB::table('titan_module_vectors')
            ->where('external_id', $chunkId)
            ->where('module', $this->module);

        if ($this->companyId !== null) {
            $query->where('company_id', $this->companyId);
        }

        $query->delete();

        return ['ok' => true];
    }

    // ── Internal helpers ──────────────────────────────────────────────────────

    private function cosine(array $a, array $b): float
    {
        $dot = 0.0;
        $na  = 0.0;
        $nb  = 0.0;
        $n   = min(count($a), count($b));

        for ($i = 0; $i < $n; $i++) {
            $ai   = (float) $a[$i];
            $bi   = (float) $b[$i];
            $dot += $ai * $bi;
            $na  += $ai * $ai;
            $nb  += $bi * $bi;
        }

        if ($na <= 0.0 || $nb <= 0.0) {
            return 0.0;
        }

        return $dot / (sqrt($na) * sqrt($nb));
    }
}
