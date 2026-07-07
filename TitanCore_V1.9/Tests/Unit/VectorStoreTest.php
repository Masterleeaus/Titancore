<?php

namespace Modules\TitanCore\Tests\Unit;

use Modules\TitanCore\Contracts\AI\IndexingContract;
use Modules\TitanCore\Contracts\AI\RetrievalContract;
use Modules\TitanCore\Contracts\AI\VectorStoreContract;
use Modules\TitanCore\AI\VectorStore\PgvectorStore;
use Modules\TitanCore\AI\VectorStore\QdrantStore;
use Modules\TitanCore\AI\VectorStore\PineconeStore;
use Modules\TitanCore\AI\VectorStore\MeilisearchScoutBridge;
use Modules\TitanCore\AI\VectorStore\VectorStoreFactory;
use PHPUnit\Framework\TestCase;

/**
 * Unit-level smoke tests for the vector store contracts, implementations and
 * factory.  These tests do not hit any real backends; they only verify the
 * class/interface relationships and static wiring.
 */
class VectorStoreTest extends TestCase
{
    // ── Contract existence ────────────────────────────────────────────────────

    public function test_vector_store_contract_is_interface(): void
    {
        $this->assertTrue(interface_exists(VectorStoreContract::class));
    }

    public function test_vector_store_contract_extends_indexing_contract(): void
    {
        $parents = class_implements(VectorStoreContract::class) ?: [];
        $this->assertContains(IndexingContract::class, $parents);
    }

    public function test_vector_store_contract_extends_retrieval_contract(): void
    {
        $parents = class_implements(VectorStoreContract::class) ?: [];
        $this->assertContains(RetrievalContract::class, $parents);
    }

    // ── Backend class existence ───────────────────────────────────────────────

    public function test_pgvector_store_class_exists(): void
    {
        $this->assertTrue(class_exists(PgvectorStore::class));
    }

    public function test_qdrant_store_class_exists(): void
    {
        $this->assertTrue(class_exists(QdrantStore::class));
    }

    public function test_pinecone_store_class_exists(): void
    {
        $this->assertTrue(class_exists(PineconeStore::class));
    }

    public function test_meilisearch_scout_bridge_class_exists(): void
    {
        $this->assertTrue(class_exists(MeilisearchScoutBridge::class));
    }

    // ── Backend implements VectorStoreContract ────────────────────────────────

    public function test_pgvector_store_implements_vector_store_contract(): void
    {
        $this->assertTrue(is_a(PgvectorStore::class, VectorStoreContract::class, true));
    }

    public function test_qdrant_store_implements_vector_store_contract(): void
    {
        $this->assertTrue(is_a(QdrantStore::class, VectorStoreContract::class, true));
    }

    public function test_pinecone_store_implements_vector_store_contract(): void
    {
        $this->assertTrue(is_a(PineconeStore::class, VectorStoreContract::class, true));
    }

    public function test_meilisearch_scout_bridge_implements_vector_store_contract(): void
    {
        $this->assertTrue(is_a(MeilisearchScoutBridge::class, VectorStoreContract::class, true));
    }

    // ── VectorStoreFactory class exists ──────────────────────────────────────

    public function test_vector_store_factory_class_exists(): void
    {
        $this->assertTrue(class_exists(VectorStoreFactory::class));
    }

    // ── VectorStoreContract method signatures ─────────────────────────────────

    public function test_vector_store_contract_has_index_method(): void
    {
        $ref = new \ReflectionMethod(VectorStoreContract::class, 'index');
        $this->assertSame('index', $ref->getName());
        $this->assertCount(3, $ref->getParameters());
    }

    public function test_vector_store_contract_has_delete_method(): void
    {
        $ref = new \ReflectionMethod(VectorStoreContract::class, 'delete');
        $this->assertSame('delete', $ref->getName());
        $this->assertCount(1, $ref->getParameters());
    }

    public function test_vector_store_contract_has_retrieve_method(): void
    {
        $ref = new \ReflectionMethod(VectorStoreContract::class, 'retrieve');
        $this->assertSame('retrieve', $ref->getName());
        $this->assertCount(3, $ref->getParameters());
    }

    // ── ReindexModuleJob class exists ─────────────────────────────────────────

    public function test_reindex_module_job_class_exists(): void
    {
        $this->assertTrue(class_exists(\Modules\TitanCore\Jobs\ReindexModuleJob::class));
    }

    public function test_reindex_module_job_implements_should_queue(): void
    {
        $this->assertTrue(
            is_a(
                \Modules\TitanCore\Jobs\ReindexModuleJob::class,
                \Illuminate\Contracts\Queue\ShouldQueue::class,
                true,
            )
        );
    }
}
