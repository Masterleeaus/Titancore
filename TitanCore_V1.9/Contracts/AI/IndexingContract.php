<?php

namespace Modules\TitanCore\Contracts\AI;

interface IndexingContract
{
    /**
     * Index a document or record into the AI knowledge base.
     *
     * @param  string  $id        Stable external identifier for the document.
     * @param  string  $content   Plain-text content to embed and store.
     * @param  array   $metadata  Arbitrary metadata (company_id, module, record_type, …).
     * @return array              ['ok'=>bool, 'id'=>string, 'chunks'=>int]
     */
    public function index(string $id, string $content, array $metadata = []): array;

    /**
     * Remove a previously indexed document from the knowledge base.
     *
     * @param  string  $id  The same identifier used when indexing.
     * @return array        ['ok'=>bool]
     */
    public function delete(string $id): array;
}
