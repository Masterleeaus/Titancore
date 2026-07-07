<?php

namespace Modules\TitanCore\AI\VectorStore;

use Illuminate\Contracts\Container\Container;
use Modules\TitanCore\Contracts\AI\VectorStoreContract;
use Modules\TitanCore\Services\EmbeddingService;

/**
 * VectorStoreFactory
 *
 * Resolves the configured VectorStoreContract backend from the IoC container.
 * The active driver is controlled by:
 *
 *   TITAN_VECTOR_STORE_DRIVER=pgvector   # pgvector | qdrant | pinecone | meilisearch
 *
 * All backend instances are singletons within the container lifetime so that
 * HTTP connection pools are reused across multiple calls per request.
 */
class VectorStoreFactory
{
    public static function make(Container $app): VectorStoreContract
    {
        $driver = config('titan-ai.vector_store.driver', 'pgvector');

        return match ($driver) {
            'qdrant'       => static::makeQdrant($app),
            'pinecone'     => static::makePinecone($app),
            'meilisearch'  => static::makeMeilisearch($app),
            default        => static::makePgvector($app),   // 'pgvector' + fallback
        };
    }

    // ── Backend constructors ──────────────────────────────────────────────────

    private static function makePgvector(Container $app): PgvectorStore
    {
        $cfg = config('titan-ai.vector_store.pgvector', []);

        return new PgvectorStore(
            embeddings: $app->make(EmbeddingService::class),
            dimensions: (int) ($cfg['dimensions'] ?? 1536),
        );
    }

    private static function makeQdrant(Container $app): QdrantStore
    {
        $cfg = config('titan-ai.vector_store.qdrant', []);

        return new QdrantStore(
            embeddings:  $app->make(EmbeddingService::class),
            host:        rtrim((string) ($cfg['host'] ?? 'http://localhost:6333'), '/'),
            collection:  (string) ($cfg['collection'] ?? 'titan_vectors'),
            apiKey:      $cfg['api_key'] ?: null,
            dimensions:  (int) ($cfg['dimensions'] ?? 1536),
        );
    }

    private static function makePinecone(Container $app): PineconeStore
    {
        $cfg = config('titan-ai.vector_store.pinecone', []);

        return new PineconeStore(
            embeddings: $app->make(EmbeddingService::class),
            apiKey:     (string) ($cfg['api_key'] ?? ''),
            indexHost:  rtrim((string) ($cfg['index_host'] ?? ''), '/'),
            namespace:  (string) ($cfg['namespace'] ?? ''),
        );
    }

    private static function makeMeilisearch(Container $app): MeilisearchScoutBridge
    {
        $cfg = config('titan-ai.vector_store.meilisearch', []);

        return new MeilisearchScoutBridge(
            embeddings: $app->make(EmbeddingService::class),
            host:       rtrim((string) ($cfg['host'] ?? 'http://localhost:7700'), '/'),
            apiKey:     (string) ($cfg['api_key'] ?? ''),
            indexName:  (string) ($cfg['index'] ?? 'titan_vectors'),
            dimensions: (int) ($cfg['dimensions'] ?? 1536),
        );
    }
}
