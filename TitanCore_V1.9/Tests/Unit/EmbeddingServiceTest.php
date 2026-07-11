<?php

namespace Modules\TitanCore\Tests\Unit;

use Modules\TitanCore\Services\EmbeddingService;
use Modules\TitanCore\Services\TitanCoreModelGateway;
use PHPUnit\Framework\TestCase;

class EmbeddingServiceTest extends TestCase
{
    public function test_embed_text_returns_normalized_vector(): void
    {
        $gateway = new class extends TitanCoreModelGateway {
            public array $calls = [];

            public function embed(string|array $input, array $context = [], array $options = []): array
            {
                $this->calls[] = compact('input', 'context', 'options');

                return [
                    'ok' => true,
                    'vectors' => [[1.0, 2.0, 3.0]],
                    'model' => 'text-embedding-test',
                    'provider' => 'openai',
                ];
            }
        };

        $service = new class($gateway) extends EmbeddingService {
            public array $writes = [];

            protected function resolveSourceId(?int $companyId, string $displayName): int
            {
                return 11;
            }

            protected function resolveCollectionId(string $collection, ?int $companyId): int
            {
                return 22;
            }

            protected function upsertDocument(
                int $sourceId,
                string $externalId,
                string $title,
                ?int $companyId,
                string $collection,
                array $options = [],
            ): int {
                return 33;
            }

            protected function attachDocumentToCollection(int $collectionId, int $documentId): void
            {
                $this->writes[] = ['attach', $collectionId, $documentId];
            }

            protected function replaceChunksForDocument(int $documentId): void
            {
                $this->writes[] = ['replace', $documentId];
            }

            protected function insertChunk(
                int $documentId,
                int $chunkIndex,
                string $content,
                array $embedding,
                int $tokens,
                array $meta = [],
            ): void {
                $this->writes[] = ['chunk', $documentId, $chunkIndex, $content, $embedding, $tokens, $meta];
            }
        };

        $result = $service->embedText('hello world', ['model' => 'text-embedding-test']);

        $this->assertSame([1.0, 2.0, 3.0], $result['vector']);
        $this->assertSame('text-embedding-test', $result['model']);
    }

    public function test_ingest_document_from_raw_chunks_and_persists_each_chunk(): void
    {
        $gateway = new class extends TitanCoreModelGateway {
            public function embed(string|array $input, array $context = [], array $options = []): array
            {
                $texts = is_array($input) ? array_values($input) : [$input];
                $vectors = [];

                foreach ($texts as $text) {
                    $vectors[] = [strlen($text), strlen($text) + 1];
                }

                return [
                    'ok' => true,
                    'vectors' => $vectors,
                    'model' => 'text-embedding-test',
                    'provider' => 'openai',
                ];
            }
        };

        $service = new class($gateway) extends EmbeddingService {
            public array $writes = [];

            protected function resolveSourceId(?int $companyId, string $displayName): int
            {
                return 11;
            }

            protected function resolveCollectionId(string $collection, ?int $companyId): int
            {
                return 22;
            }

            protected function upsertDocument(
                int $sourceId,
                string $externalId,
                string $title,
                ?int $companyId,
                string $collection,
                array $options = [],
            ): int {
                return 33;
            }

            protected function attachDocumentToCollection(int $collectionId, int $documentId): void
            {
                $this->writes[] = ['attach', $collectionId, $documentId];
            }

            protected function replaceChunksForDocument(int $documentId): void
            {
                $this->writes[] = ['replace', $documentId];
            }

            protected function insertChunk(
                int $documentId,
                int $chunkIndex,
                string $content,
                array $embedding,
                int $tokens,
                array $meta = [],
            ): void {
                $this->writes[] = ['chunk', $documentId, $chunkIndex, $content, $embedding, $tokens, $meta];
            }
        };

        $result = $service->ingestDocumentFromRaw(
            'doc-1',
            'Example',
            'alpha beta gamma delta epsilon zeta eta theta iota kappa lambda mu',
            'collection_a',
            77,
            ['chunk_size' => 24, 'chunk_overlap' => 4],
        );

        $this->assertTrue($result['ok']);
        $this->assertSame('doc-1', $result['id']);
        $this->assertGreaterThan(1, $result['chunks']);
        $this->assertContains(['attach', 22, 33], $service->writes);
        $this->assertContains(['replace', 33], $service->writes);
        $this->assertGreaterThan(1, count(array_filter($service->writes, static fn ($row) => $row[0] === 'chunk')));
    }
}
