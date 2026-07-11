<?php

namespace Modules\TitanCore\AI\VectorStore;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\TitanCore\Contracts\AI\VectorStoreContract;
use Modules\TitanCore\Services\EmbeddingService;

/**
 * PgvectorStore
 *
 * Uses the titan_module_vectors table as the persistent store.  When running
 * on PostgreSQL with the pgvector extension the native `embedding_vector`
 * column and the `<=>` (cosine) operator are used for sub-millisecond ANN
 * search.  On other drivers (SQLite for tests, MySQL) the JSON embedding
 * column is loaded and cosine similarity is computed in PHP.
 */
class PgvectorStore implements VectorStoreContract
{
    public function __construct(
        private readonly EmbeddingService $embeddings,
        private readonly int $dimensions = 1536,
    ) {}

    // ── IndexingContract ──────────────────────────────────────────────────────

    public function index(string $id, string $content, array $metadata = []): array
    {
        $emb = $this->embeddings->embedText($content);

        if (isset($emb['error'])) {
            return ['ok' => false, 'id' => $id, 'chunks' => 0, 'error' => $emb['error']];
        }

        $vector = $emb['vector'] ?? null;
        $vectorJson = $vector ? json_encode($vector) : null;

        $row = [
            'external_id' => $id,
            'company_id'  => $metadata['company_id'] ?? null,
            'module'      => $metadata['module'] ?? null,
            'content'     => $content,
            'embedding'   => $vectorJson,
            'metadata'    => json_encode($metadata),
            'updated_at'  => now(),
            'created_at'  => now(),
        ];

        DB::table('titan_module_vectors')->upsert(
            [$row],
            ['external_id'],
            ['content', 'embedding', 'metadata', 'updated_at'],
        );

        // For Postgres + pgvector: populate the native vector column
        if ($vector && $this->hasPgvectorColumn()) {
            $pgLiteral = '['.implode(',', array_map('floatval', $vector)).']';
            DB::table('titan_module_vectors')
                ->where('external_id', $id)
                ->update(['embedding_vector' => DB::raw("'{$pgLiteral}'::vector")]);
        }

        return ['ok' => true, 'id' => $id, 'chunks' => 1];
    }

    public function delete(string $id): array
    {
        DB::table('titan_module_vectors')->where('external_id', $id)->delete();

        return ['ok' => true];
    }

    // ── RetrievalContract ─────────────────────────────────────────────────────

    public function retrieve(string $query, array $context = [], int $maxResults = 5): array
    {
        $emb = $this->embeddings->embedText($query);

        if (isset($emb['error']) || empty($emb['vector'])) {
            return [];
        }

        $qVec = $emb['vector'];

        if ($this->hasPgvectorColumn()) {
            return $this->pgvectorSearch($qVec, $context, $maxResults);
        }

        return $this->phpCosineSearch($qVec, $context, $maxResults);
    }

    // ── Internal helpers ──────────────────────────────────────────────────────

    /** True when on PostgreSQL and the embedding_vector column exists. */
    private function hasPgvectorColumn(): bool
    {
        return DB::getDriverName() === 'pgsql'
            && Schema::hasColumn('titan_module_vectors', 'embedding_vector');
    }

    /**
     * Native pgvector cosine search using the <=> operator.
     * Returns results ordered by cosine distance ascending (closest first).
     */
    private function pgvectorSearch(array $qVec, array $context, int $maxResults): array
    {
        $pgLiteral = '['.implode(',', array_map('floatval', $qVec)).']';

        $query = DB::table('titan_module_vectors')
            ->whereNotNull('embedding_vector')
            ->selectRaw(
                'external_id, content, metadata, module, company_id,'
                ." (embedding_vector <=> '{$pgLiteral}'::vector) AS distance"
            )
            ->orderByRaw("embedding_vector <=> '{$pgLiteral}'::vector")
            ->limit($maxResults);

        $this->applyContextFilters($query, $context);

        return $query->get()->map(fn ($row) => [
            'content'  => $row->content,
            'score'    => round(1.0 - (float) $row->distance, 6),
            'source'   => $row->external_id,
            'module'   => $row->module,
            'metadata' => json_decode($row->metadata ?? '{}', true),
        ])->all();
    }

    /**
     * PHP-space cosine similarity fallback for non-pgvector environments.
     * Loads a bounded candidate set (200 rows) to keep memory predictable.
     */
    private function phpCosineSearch(array $qVec, array $context, int $maxResults): array
    {
        $query = DB::table('titan_module_vectors')
            ->whereNotNull('embedding')
            ->select(['external_id', 'content', 'embedding', 'metadata', 'module'])
            ->orderByDesc('id')
            ->limit(200);

        $this->applyContextFilters($query, $context);

        $scored = [];

        foreach ($query->get() as $row) {
            $v = json_decode($row->embedding, true);

            if (! is_array($v) || empty($v)) {
                continue;
            }

            $scored[] = [
                'content'  => $row->content,
                'score'    => $this->cosine($qVec, $v),
                'source'   => $row->external_id,
                'module'   => $row->module,
                'metadata' => json_decode($row->metadata ?? '{}', true),
            ];
        }

        usort($scored, fn ($a, $b) => $b['score'] <=> $a['score']);

        return array_slice($scored, 0, $maxResults);
    }

    private function applyContextFilters(\Illuminate\Database\Query\Builder $query, array $context): void
    {
        if (! empty($context['company_id'])) {
            $query->where('company_id', $context['company_id']);
        }

        if (! empty($context['module'])) {
            $query->where('module', $context['module']);
        }
    }

    private function cosine(array $a, array $b): float
    {
        $dot = 0.0;
        $na  = 0.0;
        $nb  = 0.0;
        $n   = min(count($a), count($b));

        for ($i = 0; $i < $n; $i++) {
            $ai  = (float) $a[$i];
            $bi  = (float) $b[$i];
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
