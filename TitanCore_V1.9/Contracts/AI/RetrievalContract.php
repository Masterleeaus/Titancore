<?php

namespace Modules\TitanCore\Contracts\AI;

interface RetrievalContract
{
    /**
     * Retrieve relevant context chunks for the given query.
     *
     * @param  string  $query       Natural-language query text.
     * @param  array   $context     Optional filter/tenant context: ['company_id'=>int, ...]
     * @param  int     $maxResults  Upper bound on returned chunks.
     * @return array                Array of result items: [['content'=>string, 'score'=>float, 'source'=>string], ...]
     */
    public function retrieve(string $query, array $context = [], int $maxResults = 5): array;
}
