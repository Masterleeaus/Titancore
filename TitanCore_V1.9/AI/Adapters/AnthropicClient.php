<?php

namespace Modules\TitanCore\AI\Adapters;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\TitanCore\AI\ClientInterface;
use Modules\TitanCore\Services\UsageLogger;

class AnthropicClient implements ClientInterface
{
    protected const DISABLED_REASON = 'Direct Anthropic calls are disabled in TitanCore Pass 1. Use the TitanZero gateway.';
    protected const ANTHROPIC_VERSION = '2023-06-01';

    protected string $apiKey;
    protected string $openAiApiKey;
    protected string $model;
    protected string $embeddingModel;
    protected string $provider = 'anthropic';

    public function __construct()
    {
        $this->apiKey = (string) (config('ai.providers.anthropic.api_key') ?? env('ANTHROPIC_API_KEY', ''));
        $this->openAiApiKey = (string) (config('ai.providers.openai.api_key') ?? env('OPENAI_API_KEY', ''));
        $this->model  = (string) config('ai.providers.anthropic.model', 'claude-3-haiku-20240307');
        $this->embeddingModel = (string) config('ai.providers.anthropic.embedding_model', 'text-embedding-3-small');
    }

    public function chat(array $messages, array $opts = []): array
    {
        if (!$this->apiKey) {
            return ['ok' => false, 'content' => null, 'usage' => null, 'reason' => 'Missing ANTHROPIC_API_KEY'];
        }

        return ['ok' => false, 'content' => null, 'usage' => null, 'reason' => self::DISABLED_REASON];
    }

    public function embed(array $input, array $opts = []): array
    {
        if (!$this->openAiApiKey) {
            return ['ok' => false, 'vector' => null, 'reason' => 'Anthropic does not support embeddings and OPENAI_API_KEY is missing'];
        }

        return ['ok' => false, 'vector' => null, 'reason' => self::DISABLED_REASON];
    }

    public function health(): array
    {
        return ['ok' => false, 'provider' => $this->provider, 'reason' => $this->apiKey ? self::DISABLED_REASON : 'Missing API key'];
    }

    protected function logUsage(int $tokens): void
    {
        $tenantId = null;

        if (function_exists('auth')) {
            try {
                $user = auth()->user();
                $tenantId = $user->tenant_id ?? null;
            } catch (\Throwable) {
                $tenantId = null;
            }
        }

        $key = $tenantId ? ('tenant:' . $tenantId) : 'global';

        try {
            UsageLogger::add($key, max(0, $tokens), 1);
        } catch (\Throwable $e) {
            Log::debug('AnthropicClient: usage logging skipped.', ['error' => $e->getMessage()]);
        }
    }
}
