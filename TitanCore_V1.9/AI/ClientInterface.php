<?php

namespace Modules\TitanCore\AI;

interface ClientInterface
{
    /**
     * Simple chat completion call.
     * @param array $messages  OpenAI-style: [['role'=>'user','content'=>'...'], ...]
     * @param array $opts      model, temperature, etc.
     * @return array           normalized response: ['ok'=>bool, 'content'=>string, 'usage'=>['prompt_tokens'=>..,'completion_tokens'=>..]]
     */
    public function chat(array $messages, array $opts = []): array;

    /**
     * Minimal embeddings call.
     * @param array $input
     * @param array $opts
     * @return array ['ok'=>bool, 'vector'=>array|mixed]
     */
    public function embed(array $input, array $opts = []): array;

    /**
     * Provider health snapshot.
     * @return array ['ok'=>bool, 'provider'=>string, 'reason'=>string|null]
     */
    public function health(): array;
}
