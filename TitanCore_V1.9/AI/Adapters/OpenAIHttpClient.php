<?php

namespace Modules\TitanCore\AI\Adapters;

use Modules\TitanCore\AI\ClientInterface;
use Modules\TitanCore\AI\Http\HttpClientFactory;

use Modules\TitanCore\Services\UsageLogger;

class OpenAIHttpClient implements ClientInterface
{
    protected $client;
    protected string $apiKey;
    protected string $base;
    protected string $provider = 'openai';

    public function __construct()
    {
        $this->apiKey = config('ai.providers.openai.api_key') ?? env('OPENAI_API_KEY');
        $this->base = rtrim(config('ai.providers.openai.base', 'https://api.openai.com'), '/');
        $this->client = HttpClientFactory::make([
            'base_uri' => $this->base,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json'
            ]
        ]);
    }

    public function chat(array $messages, array $opts = []): array
    {
        if (!$this->apiKey) return ['ok'=>false, 'content'=>null, 'usage'=>null, 'reason'=>'Missing OPENAI_API_KEY'];
        $model = $opts['model'] ?? config('ai.providers.openai.model', 'gpt-4o-mini');
        $payload = ['model'=>$model, 'messages'=>$messages, 'temperature'=>$opts['temperature'] ?? 0.3];
        // NOTE: We do not perform the HTTP call here in the module; host can swap binding to this class.
        $key = optional(auth()->user())->tenant_id ? ('tenant:' . auth()->user()->tenant_id) : 'global';
        UsageLogger::add($key, 120, 1);
        return ['ok'=>true, 'content'=>'(http adapter stub: would call /v1/chat/completions)', 'usage'=>['prompt_tokens'=>0,'completion_tokens'=>0]];
    }

    public function embed(array $input, array $opts = []): array
    {
        if (!$this->apiKey) return ['ok'=>false, 'vector'=>null, 'reason'=>'Missing OPENAI_API_KEY'];
        return ['ok'=>true, 'vector'=>[0.0,0.0,0.0]];
    }

    public function health(): array
    {
        return ['ok'=>(bool)$this->apiKey, 'provider'=>$this->provider, 'reason'=>$this->apiKey?null:'Missing API key'];
    }
}
