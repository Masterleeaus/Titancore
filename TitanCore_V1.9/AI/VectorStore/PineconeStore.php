<?php

namespace Modules\TitanCore\AI\VectorStore;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Modules\TitanCore\Contracts\AI\VectorStoreContract;
use Modules\TitanCore\Services\EmbeddingService;

/**
 * PineconeStore
 *
 * Implements VectorStoreContract against a Pinecone managed index.
 * Configure via:
 *
 *   PINECONE_API_KEY=<key>
 *   PINECONE_INDEX_HOST=https://<index-name>-<project-id>.svc.<env>.pinecone.io
 *   PINECONE_NAMESPACE=           # optional; leave blank for default namespace
 */
class PineconeStore implements VectorStoreContract
{
    public function __construct(
        private readonly EmbeddingService $embeddings,
        private readonly string $apiKey,
        private readonly string $indexHost,
        private readonly string $namespace = '',
    ) {}

    // ── IndexingContract ──────────────────────────────────────────────────────

    public function index(string $id, string $content, array $metadata = []): array
    {
        $emb = $this->embeddings->embedText($content);

        if (isset($emb['error'])) {
            return ['ok' => false, 'id' => $id, 'chunks' => 0, 'error' => $emb['error']];
        }

        $body = [
            'vectors' => [[
                'id'       => $id,
                'values'   => $emb['vector'],
                'metadata' => array_merge($metadata, ['content' => $content]),
            ]],
        ];

        if ($this->namespace !== '') {
            $body['namespace'] = $this->namespace;
        }

        $response = $this->client()->post("{$this->indexHost}/vectors/upsert", $body);

        if (! $response->successful()) {
            return ['ok' => false, 'id' => $id, 'chunks' => 0, 'error' => $response->body()];
        }

        return ['ok' => true, 'id' => $id, 'chunks' => 1];
    }

    public function delete(string $id): array
    {
        $body = ['ids' => [$id]];

        if ($this->namespace !== '') {
            $body['namespace'] = $this->namespace;
        }

        $response = $this->client()->post("{$this->indexHost}/vectors/delete", $body);

        return ['ok' => $response->successful()];
    }

    // ── RetrievalContract ─────────────────────────────────────────────────────

    public function retrieve(string $query, array $context = [], int $maxResults = 5): array
    {
        $emb = $this->embeddings->embedText($query);

        if (isset($emb['error']) || empty($emb['vector'])) {
            return [];
        }

        $body = [
            'vector'          => $emb['vector'],
            'topK'            => $maxResults,
            'includeMetadata' => true,
            'includeValues'   => false,
        ];

        if ($this->namespace !== '') {
            $body['namespace'] = $this->namespace;
        }

        // Apply metadata filters when context is provided
        $filter = $this->buildFilter($context);

        if ($filter !== null) {
            $body['filter'] = $filter;
        }

        $response = $this->client()->post("{$this->indexHost}/query", $body);

        if (! $response->successful()) {
            return [];
        }

        return collect($response->json('matches', []))
            ->map(fn ($match) => [
                'content'  => $match['metadata']['content'] ?? '',
                'score'    => (float) ($match['score'] ?? 0),
                'source'   => $match['id'],
                'module'   => $match['metadata']['module'] ?? null,
                'metadata' => array_diff_key($match['metadata'] ?? [], array_flip(['content'])),
            ])
            ->all();
    }

    // ── Internal helpers ──────────────────────────────────────────────────────

    private function client(): PendingRequest
    {
        return Http::withHeaders([
            'Api-Key'      => $this->apiKey,
            'Content-Type' => 'application/json',
        ])->acceptJson()->timeout(30);
    }

    private function buildFilter(array $context): ?array
    {
        $filter = [];

        if (! empty($context['company_id'])) {
            $filter['company_id'] = ['$eq' => $context['company_id']];
        }

        if (! empty($context['module'])) {
            $filter['module'] = ['$eq' => $context['module']];
        }

        return empty($filter) ? null : $filter;
    }
}
