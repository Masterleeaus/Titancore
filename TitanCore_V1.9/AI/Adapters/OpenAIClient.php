<?php

namespace Modules\TitanCore\AI\Adapters;

use Illuminate\Support\Facades\Log;
use Modules\TitanCore\AI\ClientInterface;
use Modules\TitanCore\Services\UsageLogger;

class OpenAIClient implements ClientInterface
{
    protected const DISABLED_REASON = 'Direct OpenAI calls are disabled in TitanCore Pass 1. Use the TitanZero gateway.';

    protected string $apiKey;
    protected string $model;
    protected string $embeddingModel;
    protected string $provider = 'openai';

    public function __construct()
    {
        $this->apiKey = (string) (config('ai.providers.openai.api_key') ?? env('OPENAI_API_KEY', ''));
        $this->model = (string) config('ai.providers.openai.model', 'gpt-4o-mini');
        $this->embeddingModel = (string) config('ai.providers.openai.embedding_model', 'text-embedding-3-small');
    }

    public function chat(array $messages, array $opts = []): array
    {
        if (!$this->apiKey) {
            return ['ok' => false, 'content' => null, 'usage' => null, 'reason' => 'Missing OPENAI_API_KEY'];
        }

        return ['ok' => false, 'content' => null, 'usage' => null, 'reason' => self::DISABLED_REASON];
    }

    public function embed(array $input, array $opts = []): array
    {
        if (!$this->apiKey) {
            return ['ok' => false, 'vector' => null, 'reason' => 'Missing OPENAI_API_KEY'];
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
            Log::debug('OpenAIClient: usage logging skipped.', ['error' => $e->getMessage()]);
        }
    }
}
