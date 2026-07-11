<?php

namespace Modules\TitanCore\AI\VectorStore;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Modules\TitanCore\Contracts\AI\VectorStoreContract;
use Modules\TitanCore\Services\EmbeddingService;

/**
 * QdrantStore
 *
 * Implements VectorStoreContract against a self-hosted or cloud Qdrant cluster
 * using Qdrant's REST API.  Configure via:
 *
 *   QDRANT_HOST=http://localhost:6333
 *   QDRANT_COLLECTION=titan_vectors
 *   QDRANT_API_KEY=<optional>
 *   QDRANT_DIMENSIONS=1536
 */
class QdrantStore implements VectorStoreContract
{
    public function __construct(
        private readonly EmbeddingService $embeddings,
        private readonly string $host,
        private readonly string $collection,
        private readonly ?string $apiKey = null,
        private readonly int $dimensions = 1536,
    ) {}

    // ── IndexingContract ──────────────────────────────────────────────────────

    public function index(string $id, string $content, array $metadata = []): array
    {
        $emb = $this->embeddings->embedText($content);

        if (isset($emb['error'])) {
            return ['ok' => false, 'id' => $id, 'chunks' => 0, 'error' => $emb['error']];
        }

        $this->ensureCollection();

        $response = $this->client()
            ->put("{$this->host}/collections/{$this->collection}/points", [
                'points' => [[
                    'id'      => $this->toUuidV5($id),
                    'vector'  => $emb['vector'],
                    'payload' => array_merge($metadata, [
                        'external_id' => $id,
                        'content'     => $content,
                    ]),
                ]],
            ]);

        if (! $response->successful()) {
            return ['ok' => false, 'id' => $id, 'chunks' => 0, 'error' => $response->body()];
        }

        return ['ok' => true, 'id' => $id, 'chunks' => 1];
    }

    public function delete(string $id): array
    {
        $response = $this->client()
            ->post("{$this->host}/collections/{$this->collection}/points/delete", [
                'filter' => [
                    'must' => [[
                        'key'   => 'external_id',
                        'match' => ['value' => $id],
                    ]],
                ],
            ]);

        return ['ok' => $response->successful()];
    }

    // ── RetrievalContract ─────────────────────────────────────────────────────

    public function retrieve(string $query, array $context = [], int $maxResults = 5): array
    {
        $emb = $this->embeddings->embedText($query);

        if (isset($emb['error']) || empty($emb['vector'])) {
            return [];
        }

        $filter = $this->buildFilter($context);

        $body = [
            'vector'       => $emb['vector'],
            'limit'        => $maxResults,
            'with_payload' => true,
            'with_vector'  => false,
        ];

        if ($filter !== null) {
            $body['filter'] = $filter;
        }

        $response = $this->client()
            ->post("{$this->host}/collections/{$this->collection}/points/search", $body);

        if (! $response->successful()) {
            return [];
        }

        return collect($response->json('result', []))
            ->map(fn ($hit) => [
                'content'  => $hit['payload']['content'] ?? '',
                'score'    => (float) ($hit['score'] ?? 0),
                'source'   => $hit['payload']['external_id'] ?? (string) $hit['id'],
                'module'   => $hit['payload']['module'] ?? null,
                'metadata' => array_diff_key($hit['payload'] ?? [], array_flip(['content', 'external_id'])),
            ])
            ->all();
    }

    // ── Internal helpers ──────────────────────────────────────────────────────

    private function client(): PendingRequest
    {
        $pending = Http::baseUrl($this->host)
            ->acceptJson()
            ->contentType('application/json')
            ->timeout(30);

        if ($this->apiKey !== null) {
            $pending = $pending->withHeaders(['api-key' => $this->apiKey]);
        }

        return $pending;
    }

    /** Create the collection if it does not already exist. */
    private function ensureCollection(): void
    {
        $exists = $this->client()
            ->get("{$this->host}/collections/{$this->collection}")
            ->successful();

        if (! $exists) {
            $this->client()
                ->put("{$this->host}/collections/{$this->collection}", [
                    'vectors' => [
                        'size'     => $this->dimensions,
                        'distance' => 'Cosine',
                    ],
                ]);
        }
    }

    private function buildFilter(array $context): ?array
    {
        $must = [];

        if (! empty($context['company_id'])) {
            $must[] = ['key' => 'company_id', 'match' => ['value' => $context['company_id']]];
        }

        if (! empty($context['module'])) {
            $must[] = ['key' => 'module', 'match' => ['value' => $context['module']]];
        }

        return empty($must) ? null : ['must' => $must];
    }

    /**
     * Derive a deterministic UUIDv5 integer from an arbitrary string id so
     * that re-indexing the same document always overwrites the same Qdrant
     * point (Qdrant supports both unsigned-int and UUID point IDs).
     */
    private function toUuidV5(string $id): string
    {
        $hash = sha1('titan-vector-store:'.$id);
        // Format first 32 hex chars as UUID v5
        return sprintf(
            '%08s-%04s-5%03s-%04s-%12s',
            substr($hash, 0, 8),
            substr($hash, 8, 4),
            substr($hash, 13, 3),
            dechex(0x8000 | (hexdec(substr($hash, 16, 4)) & 0x3fff)),
            substr($hash, 20, 12),
        );
    }
}
