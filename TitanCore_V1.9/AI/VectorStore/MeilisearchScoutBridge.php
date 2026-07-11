<?php

namespace Modules\TitanCore\AI\VectorStore;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Modules\TitanCore\Contracts\AI\VectorStoreContract;
use Modules\TitanCore\Services\EmbeddingService;

/**
 * MeilisearchScoutBridge
 *
 * Hybrid keyword + semantic (vector) search via Meilisearch.
 * Requires Meilisearch ≥ v1.6 with the vector store feature enabled.
 *
 * Configure via:
 *   MEILISEARCH_HOST=http://localhost:7700
 *   MEILISEARCH_KEY=<master-or-api-key>
 *   MEILISEARCH_VECTOR_INDEX=titan_vectors
 *   MEILISEARCH_VECTOR_DIMENSIONS=1536
 *
 * Note: enable the vectors feature on your Meilisearch instance:
 *   curl -X PATCH /experimental-features -d '{"vectorStore": true}'
 */
class MeilisearchScoutBridge implements VectorStoreContract
{
    public function __construct(
        private readonly EmbeddingService $embeddings,
        private readonly string $host,
        private readonly string $apiKey,
        private readonly string $indexName = 'titan_vectors',
        private readonly int $dimensions = 1536,
    ) {}

    // ── IndexingContract ──────────────────────────────────────────────────────

    public function index(string $id, string $content, array $metadata = []): array
    {
        $emb = $this->embeddings->embedText($content);

        $document = array_merge($metadata, [
            'id'      => $id,
            'content' => $content,
        ]);

        if (! isset($emb['error']) && ! empty($emb['vector'])) {
            $document['_vectors'] = ['default' => $emb['vector']];
        }

        $response = $this->client()
            ->post("{$this->host}/indexes/{$this->indexName}/documents", [$document]);

        if (! $response->successful()) {
            return ['ok' => false, 'id' => $id, 'chunks' => 0, 'error' => $response->body()];
        }

        return ['ok' => true, 'id' => $id, 'chunks' => 1];
    }

    public function delete(string $id): array
    {
        $response = $this->client()
            ->delete("{$this->host}/indexes/{$this->indexName}/documents/{$id}");

        return ['ok' => $response->successful()];
    }

    // ── RetrievalContract ─────────────────────────────────────────────────────

    public function retrieve(string $query, array $context = [], int $maxResults = 5): array
    {
        $emb      = $this->embeddings->embedText($query);
        $hasEmbed = ! isset($emb['error']) && ! empty($emb['vector']);

        $body = [
            'q'     => $query,
            'limit' => $maxResults,
        ];

        // Hybrid: add vector when available; Meilisearch blends keyword + semantic
        if ($hasEmbed) {
            $body['vector']           = $emb['vector'];
            $body['hybrid']           = ['semanticRatio' => 0.5, 'embedder' => 'default'];
        }

        $filter = $this->buildFilter($context);

        if ($filter !== null) {
            $body['filter'] = $filter;
        }

        $response = $this->client()
            ->post("{$this->host}/indexes/{$this->indexName}/search", $body);

        if (! $response->successful()) {
            return [];
        }

        return collect($response->json('hits', []))
            ->map(fn ($hit) => [
                'content'  => $hit['content'] ?? '',
                'score'    => (float) ($hit['_rankingScore'] ?? 0),
                'source'   => $hit['id'] ?? '',
                'module'   => $hit['module'] ?? null,
                'metadata' => array_diff_key($hit, array_flip(['id', 'content', '_rankingScore', '_vectors'])),
            ])
            ->all();
    }

    // ── Internal helpers ──────────────────────────────────────────────────────

    private function client(): PendingRequest
    {
        return Http::withHeaders([
            'Authorization' => 'Bearer '.$this->apiKey,
            'Content-Type'  => 'application/json',
        ])->acceptJson()->timeout(30);
    }

    private function buildFilter(array $context): ?string
    {
        $parts = [];

        if (! empty($context['company_id'])) {
            $parts[] = 'company_id = '.(int) $context['company_id'];
        }

        if (! empty($context['module'])) {
            $module  = addslashes($context['module']);
            $parts[] = "module = \"{$module}\"";
        }

        return empty($parts) ? null : implode(' AND ', $parts);
    }
}
