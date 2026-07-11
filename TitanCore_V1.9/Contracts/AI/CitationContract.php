<?php

namespace Modules\TitanCore\Contracts\AI;

interface CitationContract
{
    /**
     * Resolve source citations for an AI-generated response.
     *
     * @param  string  $responseText  The raw AI-generated text that may reference sources.
     * @param  array   $retrievedDocs Documents returned by the retrieval step:
     *                                [['content'=>string, 'score'=>float, 'source'=>string], ...]
     * @return array                  Normalised citation list:
     *                                [['ref'=>string, 'source'=>string, 'excerpt'=>string], ...]
     */
    public function resolve(string $responseText, array $retrievedDocs): array;
}
