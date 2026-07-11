<?php

namespace Modules\TitanCore\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\TitanCore\Services\EmbeddingService;
use Modules\TitanCore\Services\KbCollectionService;
use Modules\TitanCore\Services\KbHealthService;
use Modules\TitanCore\Services\KnowledgeSearchService;

/**
 * Knowledge API — /api/v1/knowledge/*
 *
 * Exposes the knowledge subsystem: collections, documents, chunks,
 * embeddings, search, retrieval, citations, import, and export.
 *
 * Reuses existing services — does not duplicate implementations.
 */
class KnowledgeController extends Controller
{
    public function __construct(
        private readonly KbHealthService $health,
        private readonly KnowledgeSearchService $search,
        private readonly EmbeddingService $embeddings,
    ) {}

    /**
     * GET /api/v1/knowledge/collections
     *
     * List all knowledge collections.
     */
    public function collections(Request $request): JsonResponse
    {
        $tenantId = optional($request->user())->tenant_id;

        $q = DB::table('ai_kb_collections')->orderBy('updated_at', 'desc');
        if ($tenantId !== null) {
            $q->where(function ($query) use ($tenantId) {
                $query->where('tenant_id', $tenantId)->orWhereNull('tenant_id');
            });
        }

        $rows = $q->paginate((int) $request->query('per_page', 50));

        return response()->json([
            'data'  => $rows->items(),
            'meta'  => [
                'total'        => $rows->total(),
                'per_page'     => $rows->perPage(),
                'current_page' => $rows->currentPage(),
                'last_page'    => $rows->lastPage(),
            ],
        ]);
    }

    /**
     * GET /api/v1/knowledge/documents
     *
     * List documents, optionally filtered by collection key.
     */
    public function documents(Request $request): JsonResponse
    {
        $collectionKey = $request->query('collection');

        $q = DB::table('ai_kb_documents as d');

        if ($collectionKey) {
            $q->join('ai_kb_collection_docs as cd', 'cd.document_id', '=', 'd.id')
              ->join('ai_kb_collections as c', 'c.id', '=', 'cd.collection_id')
              ->where('c.key_slug', $collectionKey);
        }

        $q->select(['d.*'])->orderBy('d.updated_at', 'desc');

        $rows = $q->paginate((int) $request->query('per_page', 50));

        return response()->json([
            'data' => $rows->items(),
            'meta' => [
                'total'        => $rows->total(),
                'per_page'     => $rows->perPage(),
                'current_page' => $rows->currentPage(),
                'last_page'    => $rows->lastPage(),
            ],
        ]);
    }

    /**
     * GET /api/v1/knowledge/chunks
     *
     * List chunks for a document.
     */
    public function chunks(Request $request): JsonResponse
    {
        $documentId = $request->query('document_id');
        if (! $documentId) {
            return response()->json(['error' => 'document_id required'], 422);
        }

        $rows = DB::table('ai_kb_chunks')
            ->where('document_id', $documentId)
            ->orderBy('chunk_index')
            ->paginate((int) $request->query('per_page', 50));

        // Omit raw embedding vectors from the response
        $items = collect($rows->items())->map(function ($row) {
            $arr = (array) $row;
            unset($arr['embedding']);
            $arr['has_embedding'] = isset($row->embedding) && $row->embedding !== null;

            return $arr;
        })->all();

        return response()->json([
            'data' => $items,
            'meta' => [
                'total'        => $rows->total(),
                'per_page'     => $rows->perPage(),
                'current_page' => $rows->currentPage(),
                'last_page'    => $rows->lastPage(),
            ],
        ]);
    }

    /**
     * GET /api/v1/knowledge/embeddings
     *
     * Return embedding health summary (counts by collection).
     */
    public function embeddings(Request $request): JsonResponse
    {
        $tenantId = optional($request->user())->tenant_id;
        $summary  = $this->health->summary($tenantId);

        return response()->json([
            'data' => $summary,
            'ts'   => now()->toIso8601String(),
        ]);
    }

    /**
     * GET /api/v1/knowledge/search
     *
     * Semantic / hybrid search over a knowledge collection.
     */
    public function search(Request $request): JsonResponse
    {
        $collectionKey = $request->query('collection');
        $query         = $request->query('q', '');
        $limit         = (int) $request->query('limit', 8);
        $tenantId      = optional($request->user())->tenant_id;

        if (! $collectionKey || ! $query) {
            return response()->json(['error' => 'collection and q are required'], 422);
        }

        $results = $this->search->searchCollection($collectionKey, $query, $tenantId, $limit);

        return response()->json([
            'data'       => $results,
            'total'      => count($results),
            'collection' => $collectionKey,
            'query'      => $query,
        ]);
    }

    /**
     * POST /api/v1/knowledge/retrieve
     *
     * Retrieve context passages for a given query (alias for search with structured output).
     */
    public function retrieve(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'collection' => 'required|string|max:255',
            'query'      => 'required|string|max:2000',
            'limit'      => 'sometimes|integer|min:1|max:50',
        ]);

        $tenantId = optional($request->user())->tenant_id;
        $results  = $this->search->searchCollection(
            $validated['collection'],
            $validated['query'],
            $tenantId,
            $validated['limit'] ?? 6,
        );

        return response()->json([
            'passages'   => array_map(fn ($r) => [
                'content'        => $r['content'] ?? '',
                'score'          => $r['score'] ?? null,
                'document_id'    => $r['document_id'] ?? null,
                'document_title' => $r['document_title'] ?? null,
                'chunk_id'       => $r['chunk_id'] ?? null,
            ], $results),
            'collection' => $validated['collection'],
            'query'      => $validated['query'],
            'ts'         => now()->toIso8601String(),
        ]);
    }

    /**
     * GET /api/v1/knowledge/citations
     *
     * Retrieve citation metadata for a set of chunk IDs.
     */
    public function citations(Request $request): JsonResponse
    {
        $chunkIds = array_filter(
            array_map('intval', (array) $request->query('chunk_ids', [])),
            fn ($id) => $id > 0,
        );

        if (empty($chunkIds)) {
            return response()->json(['error' => 'chunk_ids required'], 422);
        }

        $rows = DB::table('ai_kb_chunks as ch')
            ->join('ai_kb_documents as d', 'd.id', '=', 'ch.document_id')
            ->whereIn('ch.id', $chunkIds)
            ->select(['ch.id as chunk_id', 'd.id as document_id', 'd.title', 'ch.chunk_index', 'ch.content'])
            ->get();

        return response()->json(['data' => $rows]);
    }

    /**
     * POST /api/v1/knowledge/import
     *
     * Import a document into the knowledge base.
     * Delegates to the same logic as KbApiController::ingest.
     */
    public function import(Request $request): JsonResponse
    {
        $this->authorize('ingest_ai_kb');

        $validated = $request->validate([
            'collection' => 'required|string|max:255',
            'title'      => 'sometimes|string|max:500',
            'content'    => 'required|string',
            'embed'      => 'sometimes|boolean',
        ]);

        $tenantId      = optional($request->user())->tenant_id;
        $collectionKey = $validated['collection'];
        $title         = $validated['title'] ?? 'Imported';
        $content       = $validated['content'];
        $withEmbedding = (bool) ($validated['embed'] ?? true);

        $coll = DB::table('ai_kb_collections')->where('key_slug', $collectionKey)->first();
        $cid  = $coll->id ?? DB::table('ai_kb_collections')->insertGetId([
            'tenant_id'  => $tenantId,
            'key_slug'   => $collectionKey,
            'title'      => Str::title(str_replace('_', ' ', $collectionKey)),
            'meta'       => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $sourceId = DB::table('ai_kb_sources')->insertGetId([
            'tenant_id'    => $tenantId,
            'source_type'  => 'api_import',
            'display_name' => $title,
            'meta'         => json_encode([]),
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        $docId = DB::table('ai_kb_documents')->insertGetId([
            'source_id'   => $sourceId,
            'external_id' => null,
            'title'       => $title,
            'mime'        => 'text/plain',
            'lang'        => 'en',
            'meta'        => json_encode([]),
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        DB::table('ai_kb_collection_docs')->insertOrIgnore([
            'collection_id' => $cid,
            'document_id'   => $docId,
        ]);

        $parts = preg_split("/\n\n+/", $content) ?: [];
        $idx   = 0;
        foreach ($parts as $chunk) {
            $chunk = trim($chunk);
            if ($chunk === '') {
                continue;
            }

            $embedding = null;
            if ($withEmbedding) {
                $e = $this->embeddings->embedText($chunk);
                if (! isset($e['error'])) {
                    $embedding = json_encode($e['vector']);
                }
            }

            DB::table('ai_kb_chunks')->insert([
                'document_id' => $docId,
                'chunk_index' => $idx++,
                'content'     => $chunk,
                'embedding'   => $embedding,
                'tokens'      => strlen($chunk),
                'meta'        => json_encode([]),
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        }

        return response()->json([
            'status'      => 'ok',
            'document_id' => $docId,
            'chunks'      => $idx,
            'ts'          => now()->toIso8601String(),
        ], 201);
    }

    /**
     * GET /api/v1/knowledge/export
     *
     * Export all chunks for a collection as JSON.
     */
    public function export(Request $request): JsonResponse
    {
        $collectionKey = $request->query('collection');
        if (! $collectionKey) {
            return response()->json(['error' => 'collection required'], 422);
        }

        $coll = DB::table('ai_kb_collections')->where('key_slug', $collectionKey)->first();
        if (! $coll) {
            return response()->json(['error' => 'Collection not found'], 404);
        }

        $docIds = DB::table('ai_kb_collection_docs')
            ->where('collection_id', $coll->id)
            ->pluck('document_id')
            ->toArray();

        $chunks = DB::table('ai_kb_chunks as ch')
            ->join('ai_kb_documents as d', 'd.id', '=', 'ch.document_id')
            ->whereIn('ch.document_id', $docIds)
            ->select(['ch.id', 'ch.chunk_index', 'ch.content', 'ch.tokens', 'd.title as document_title', 'd.id as document_id'])
            ->orderBy('ch.document_id')
            ->orderBy('ch.chunk_index')
            ->get();

        return response()->json([
            'collection' => $collectionKey,
            'data'       => $chunks,
            'total'      => $chunks->count(),
            'ts'         => now()->toIso8601String(),
        ]);
    }
}
