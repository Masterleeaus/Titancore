<?php

namespace TitanSDK\Contracts\AI;

/**
 * VectorStoreContract
 *
 * Unified contract for all vector-store backends.  Implementations are
 * responsible for both writing documents into the store (IndexingContract)
 * and querying it for semantically similar results (RetrievalContract).
 *
 * Switch backends via config('titan-ai.vector_store.driver'):
 *   pgvector | qdrant | pinecone | meilisearch
 */
interface VectorStoreContract extends IndexingContract, RetrievalContract
{
    // All methods inherited from IndexingContract and RetrievalContract.
    // Implementations may add driver-specific helpers but must not remove
    // the inherited signatures.
}
