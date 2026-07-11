<?php
namespace Modules\TitanCore\Services;

use Illuminate\Support\Facades\DB;
use Modules\TitanCore\Services\EmbeddingService;

/**
 * KnowledgeSearchService
 *
 * Pass 4:
 * - Hybrid search over KB collections.
 * - If embeddings are available for the query and chunks, uses cosine similarity.
 * - Falls back to keyword LIKE search when embeddings are unavailable.
 *
 * NOTE: This is intentionally simple and bounded (limits + tenant scope handled at collection selection layer).
 */
class KnowledgeSearchService
{
    public function __construct(private EmbeddingService $embeddings) {}

    /**
     * @return array<int, array{chunk_id:int, score:float, content:string, document_title:?string, document_id:int}>
     */
    public function searchCollection(string $collectionKey, string $query, ?int $tenantId = null, int $limit = 6): array
    {
        $query = trim($query);
        if ($query === '') return [];

        $collQ = DB::table('ai_kb_collections')->where('key_slug', $collectionKey);
        // Tenant override preferred, else global
        if ($tenantId !== null) {
            $coll = (clone $collQ)->where('tenant_id', $tenantId)->first()
                ?? (clone $collQ)->whereNull('tenant_id')->first();
        } else {
            $coll = (clone $collQ)->whereNull('tenant_id')->first()
                ?? (clone $collQ)->first();
        }
        if (! $coll) return [];

        $activeVersionId = null;
        if (property_exists($coll, 'active_version_id')) {
            $activeVersionId = $coll->active_version_id;
        }

        $docIds = DB::table('ai_kb_collection_docs')->where('collection_id', $coll->id)->pluck('document_id')->toArray();
        if (empty($docIds)) return [];

        // Attempt embedding for the query
        $qVec = null;
        $emb = $this->embeddings->embedText($query);
        if (!isset($emb['error']) && !empty($emb['vector'])) {
            $qVec = $emb['vector'];
        }

        // Pull a bounded set of candidate chunks (use published snapshot if active)
        if ($activeVersionId) {
            $candidates = DB::table('titan_ai_kb_chunk_snapshots')
                ->where('version_id', $activeVersionId)
                ->whereIn('document_id', $docIds)
                ->select(['id','document_id','content','embedding'])
                ->orderByDesc('id')
                ->limit(250)
                ->get()
                ->toArray();
        } else {
            $candidates = DB::table('ai_kb_chunks')
                ->whereIn('document_id', $docIds)
                ->select(['id','document_id','content','embedding'])
                ->orderByDesc('id')
                ->limit(250)
                ->get()
                ->toArray();
        }

        // If we can't embed, do keyword search
        if ($qVec === null) {
            $rows = ($activeVersionId ? DB::table('titan_ai_kb_chunk_snapshots')->where('version_id',$activeVersionId) : DB::table('ai_kb_chunks'))
                ->whereIn('document_id', $docIds)
                ->where('content','LIKE','%'.$query.'%')
                ->limit(max(10, $limit * 5))
                ->get()
                ->toArray();

            return $this->decorateResults($rows, $limit);
        }

        $scored = [];
        foreach ($candidates as $row) {
            if (!$row->embedding) continue;
            $v = json_decode($row->embedding, true);
            if (!is_array($v) || empty($v)) continue;
            $score = $this->cosine($qVec, $v);
            $scored[] = ['row'=>$row, 'score'=>$score];
        }

        usort($scored, fn($a,$b) => $b['score'] <=> $a['score']);
        $top = array_slice($scored, 0, max(15, $limit * 3));
        $rows = array_map(fn($x) => $x['row'], $top);

        // Attach scores and decorate
        $decorated = $this->decorateResults($rows, $limit, $scored);
        return $decorated;
    }

    private function decorateResults(array $rows, int $limit, ?array $scored=null): array
    {
        $docIds = array_values(array_unique(array_map(fn($r) => (int)$r->document_id, $rows)));
        $titles = DB::table('ai_kb_documents')->whereIn('id', $docIds)->pluck('title','id')->toArray();

        // Map scores if provided
        $scoreMap = [];
        if ($scored) {
            foreach ($scored as $s) {
                $scoreMap[(int)$s['row']->id] = (float)$s['score'];
            }
        }

        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'chunk_id' => (int)$r->id,
                'score' => $scoreMap[(int)$r->id] ?? 0.0,
                'content' => (string)$r->content,
                'document_title' => $titles[(int)$r->document_id] ?? null,
                'document_id' => (int)$r->document_id,
            ];
        }

        // If no scores, do a rough relevance by substring presence
        if (!$scored) {
            foreach ($out as &$o) {
                $o['score'] = 0.25;
            }
        }

        // Keep unique by chunk_id and limit
        $seen = [];
        $final = [];
        foreach ($out as $o) {
            if (isset($seen[$o['chunk_id']])) continue;
            $seen[$o['chunk_id']] = true;
            $final[] = $o;
            if (count($final) >= $limit) break;
        }
        return $final;
    }

    private function cosine(array $a, array $b): float
    {
        $dot = 0.0; $na = 0.0; $nb = 0.0;
        $n = min(count($a), count($b));
        for ($i=0; $i<$n; $i++) {
            $ai = (float)$a[$i]; $bi = (float)$b[$i];
            $dot += $ai * $bi;
            $na  += $ai * $ai;
            $nb  += $bi * $bi;
        }
        if ($na <= 0 || $nb <= 0) return 0.0;
        return $dot / (sqrt($na) * sqrt($nb));
    }
}
