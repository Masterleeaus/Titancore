<?php

namespace Modules\TitanCore\AI\Adapters;

use Modules\TitanCore\AI\ClientInterface;

class OpenAIHttpClient implements ClientInterface
{
    protected string $apiKey;
    protected string $provider = 'openai';
    protected const DISABLED_REASON = 'Direct OpenAI calls are disabled in TitanCore Pass 1. Use the TitanZero gateway.';

    public function __construct()
    {
        $this->apiKey = config('ai.providers.openai.api_key') ?? env('OPENAI_API_KEY');
    }

    public function chat(array $messages, array $opts = []): array
    {
        if (!$this->apiKey) return ['ok'=>false, 'content'=>null, 'usage'=>null, 'reason'=>'Missing OPENAI_API_KEY'];
        return ['ok'=>false, 'content'=>null, 'usage'=>null, 'reason'=>self::DISABLED_REASON];
    }

    public function embed(array $input, array $opts = []): array
    {
        if (!$this->apiKey) return ['ok'=>false, 'vector'=>null, 'reason'=>'Missing OPENAI_API_KEY'];
        return ['ok'=>false, 'vector'=>null, 'reason'=>self::DISABLED_REASON];
    }

    public function health(): array
    {
        return ['ok'=>false, 'provider'=>$this->provider, 'reason'=>$this->apiKey?self::DISABLED_REASON:'Missing API key'];
    }
}
