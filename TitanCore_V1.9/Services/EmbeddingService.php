<?php

namespace Modules\TitanCore\Services;

use Illuminate\Support\Facades\DB;

class EmbeddingService
{
    public function __construct(private TitanCoreModelGateway $gateway) {}

    public function embedText(string $text, array $opts = []): array
    {
        $batch = $this->embedBatch([$text], $opts);

        return $batch[0] ?? [
            'error' => 'No embedding returned',
            'vector' => [],
            'raw' => ['ok' => false],
        ];
    }

    public function embedBatch(array $texts, array $opts = []): array
    {
        $texts = array_values(array_map(static fn ($text) => (string) $text, $texts));

        if ($texts === []) {
            return [];
        }

        $result = $this->gateway->embed($texts, [], $opts);

        if (!($result['ok'] ?? false)) {
            return array_map(
                static fn () => [
                    'error' => $result['error'] ?? 'Embedding failed',
                    'vector' => [],
                    'raw' => $result,
                ],
                $texts,
            );
        }

        $vectors = $result['vectors'] ?? [];
        if (!is_array($vectors)) {
            $vectors = [];
        }

        $normalized = [];
        foreach ($texts as $index => $text) {
            $normalized[] = [
                'vector' => $vectors[$index] ?? [],
                'raw' => $result,
                'model' => $result['model'] ?? ($opts['model'] ?? null),
            ];
        }

        return $normalized;
    }

    public function ingestDocumentFromRaw(
        string $externalId,
        string $title,
        string $rawContent,
        string $collection,
        ?int $companyId = null,
        array $options = [],
    ): array {
        $content = trim(preg_replace('/\s+/u', ' ', $rawContent) ?? $rawContent);

        if ($content === '') {
            return ['ok' => false, 'error' => 'Empty document content'];
        }

        $chunkSize = max(1, (int) ($options['chunk_size'] ?? 900));
        $overlap = max(0, min($chunkSize - 1, (int) ($options['chunk_overlap'] ?? 120)));
        $chunks = $this->chunkContent($content, $chunkSize, $overlap);

        if ($chunks === []) {
            return ['ok' => false, 'error' => 'No chunks generated'];
        }

        $sourceId = $this->resolveSourceId($companyId, (string) ($options['source_name'] ?? 'TitanCore'));
        $documentId = $this->upsertDocument($sourceId, $externalId, $title, $companyId, $collection, $options);
        $collectionId = $this->resolveCollectionId($collection, $companyId);

        $this->attachDocumentToCollection($collectionId, $documentId);
        $this->replaceChunksForDocument($documentId);

        $chunkCount = 0;
        foreach ($chunks as $index => $chunk) {
            $embedding = $this->embedText($chunk, $options);
            $vector = $embedding['vector'] ?? [];
            $this->insertChunk(
                $documentId,
                $index,
                $chunk,
                $vector,
                $this->estimateTokens($chunk),
                [
                    'external_id' => $externalId,
                    'collection' => $collection,
                    'company_id' => $companyId,
                    'chunk_index' => $index,
                ],
            );
            $chunkCount++;
        }

        return [
            'ok' => true,
            'id' => $externalId,
            'document_id' => $documentId,
            'collection_id' => $collectionId,
            'chunks' => $chunkCount,
            'source_id' => $sourceId,
        ];
    }

    protected function resolveSourceId(?int $companyId, string $displayName): int
    {
        if (!DB::getSchemaBuilder()->hasTable('ai_kb_sources')) {
            return 0;
        }

        $query = DB::table('ai_kb_sources')->where('display_name', $displayName);
        $hasCompanyColumn = DB::getSchemaBuilder()->hasColumn('ai_kb_sources', 'company_id');
        $hasTenantColumn = DB::getSchemaBuilder()->hasColumn('ai_kb_sources', 'tenant_id');
        if ($companyId !== null) {
            $row = $hasCompanyColumn ? (clone $query)->where('company_id', $companyId)->first() : null;
            $row ??= $hasCompanyColumn ? (clone $query)->whereNull('company_id')->first() : null;
            $row ??= $hasTenantColumn ? (clone $query)->where('tenant_id', $companyId)->first() : null;
            $row ??= $hasTenantColumn ? (clone $query)->whereNull('tenant_id')->first() : null;
        } else {
            $row = $hasCompanyColumn ? (clone $query)->whereNull('company_id')->first() : null;
            $row ??= $hasTenantColumn ? (clone $query)->whereNull('tenant_id')->first() : null;
            $row ??= (clone $query)->first();
        }

        if ($row) {
            return (int) $row->id;
        }

        $payload = [
            'source_type' => 'api',
            'display_name' => $displayName,
            'meta' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ];
        if ($hasCompanyColumn) {
            $payload['company_id'] = $companyId;
        }
        if ($hasTenantColumn) {
            $payload['tenant_id'] = $companyId;
        }

        return (int) DB::table('ai_kb_sources')->insertGetId($payload);
    }

    protected function resolveCollectionId(string $collection, ?int $companyId): int
    {
        if (!DB::getSchemaBuilder()->hasTable('ai_kb_collections')) {
            return 0;
        }

        $query = DB::table('ai_kb_collections')->where('key_slug', $collection);
        $hasCompanyColumn = DB::getSchemaBuilder()->hasColumn('ai_kb_collections', 'company_id');
        $hasTenantColumn = DB::getSchemaBuilder()->hasColumn('ai_kb_collections', 'tenant_id');
        if ($companyId !== null) {
            $row = $hasCompanyColumn ? (clone $query)->where('company_id', $companyId)->first() : null;
            $row ??= $hasCompanyColumn ? (clone $query)->whereNull('company_id')->first() : null;
            $row ??= $hasTenantColumn ? (clone $query)->where('tenant_id', $companyId)->first() : null;
            $row ??= $hasTenantColumn ? (clone $query)->whereNull('tenant_id')->first() : null;
        } else {
            $row = $hasCompanyColumn ? (clone $query)->whereNull('company_id')->first() : null;
            $row ??= $hasTenantColumn ? (clone $query)->whereNull('tenant_id')->first() : null;
            $row ??= (clone $query)->first();
        }

        if ($row) {
            return (int) $row->id;
        }

        $payload = [
            'key_slug' => $collection,
            'title' => ucfirst(str_replace('_', ' ', $collection)),
            'meta' => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ];
        if ($hasCompanyColumn) {
            $payload['company_id'] = $companyId;
        }
        if ($hasTenantColumn) {
            $payload['tenant_id'] = $companyId;
        }

        return (int) DB::table('ai_kb_collections')->insertGetId($payload);
    }

    protected function upsertDocument(
        int $sourceId,
        string $externalId,
        string $title,
        ?int $companyId,
        string $collection,
        array $options = [],
    ): int {
        if (!DB::getSchemaBuilder()->hasTable('ai_kb_documents')) {
            return 0;
        }

        $row = DB::table('ai_kb_documents')
            ->where('source_id', $sourceId)
            ->where('external_id', $externalId)
            ->first();

        $payload = [
            'source_id' => $sourceId,
            'external_id' => $externalId,
            'title' => $title,
            'mime' => $options['mime'] ?? 'text/plain',
            'lang' => $options['lang'] ?? 'en',
            'meta' => json_encode([
                'collection' => $collection,
                'company_id' => $companyId,
            ]),
            'updated_at' => now(),
        ];

        if ($row) {
            DB::table('ai_kb_documents')->where('id', $row->id)->update($payload);
            return (int) $row->id;
        }

        $payload['created_at'] = now();

        return (int) DB::table('ai_kb_documents')->insertGetId($payload);
    }

    protected function attachDocumentToCollection(int $collectionId, int $documentId): void
    {
        if (!DB::getSchemaBuilder()->hasTable('ai_kb_collection_docs')) {
            return;
        }

        DB::table('ai_kb_collection_docs')->updateOrInsert([
            'collection_id' => $collectionId,
            'document_id' => $documentId,
        ], []);
    }

    protected function replaceChunksForDocument(int $documentId): void
    {
        if (DB::getSchemaBuilder()->hasTable('ai_kb_chunks')) {
            DB::table('ai_kb_chunks')->where('document_id', $documentId)->delete();
        }
    }

    protected function insertChunk(
        int $documentId,
        int $chunkIndex,
        string $content,
        array $embedding,
        int $tokens,
        array $meta = [],
    ): void {
        if (!DB::getSchemaBuilder()->hasTable('ai_kb_chunks')) {
            return;
        }

        DB::table('ai_kb_chunks')->insert([
            'document_id' => $documentId,
            'chunk_index' => $chunkIndex,
            'content' => $content,
            'embedding' => json_encode(array_values($embedding)),
            'tokens' => $tokens,
            'meta' => json_encode($meta),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function chunkContent(string $content, int $chunkSize, int $overlap): array
    {
        $content = trim(preg_replace('/\s+/u', ' ', $content) ?? $content);
        if ($content === '') {
            return [];
        }

        $chunks = [];
        $length = mb_strlen($content);
        $offset = 0;

        while ($offset < $length) {
            $end = min($length, $offset + $chunkSize);
            $chunk = trim(mb_substr($content, $offset, $end - $offset));

            if ($chunk !== '') {
                $chunks[] = $chunk;
            }

            if ($end >= $length) {
                break;
            }

            $offset = max(0, $end - $overlap);
        }

        return $chunks;
    }

    protected function estimateTokens(string $text): int
    {
        $text = trim($text);
        if ($text === '') {
            return 0;
        }

        return max(1, (int) ceil(mb_strlen($text) / 4));
    }
}
