<?php

namespace Modules\TitanCore\Services;

use stdClass;

class TitanCoreAIService
{
    public function __construct(private TitanCoreModelGateway $gateway) {}

    public function isProviderAvailable(string $provider): bool
    {
        return in_array($provider, ['openai', 'anthropic', 'local', 'ollama'], true);
    }

    public function embed(string $text, string $model): array
    {
        return $this->embedBatch([$text], $model)[0] ?? [];
    }

    public function embedBatch(array $texts, string $model): array
    {
        $result = $this->gateway->embed($texts, [], [
            'provider' => $this->normalizeEmbeddingProvider('openai'),
            'model' => $model,
        ]);

        if (!($result['ok'] ?? false)) {
            return array_map(static fn () => ['error' => $result['error'] ?? 'Embedding failed'], $texts);
        }

        $vectors = $result['vectors'] ?? [];
        $vectors = is_array($vectors) ? $vectors : [];

        $out = [];
        foreach (array_values($texts) as $index => $text) {
            $out[] = [
                'vector' => $vectors[$index] ?? [],
                'raw' => $result,
            ];
        }

        return $out;
    }

    public function generateOpenAI(string $prompt, array $messages = [], array $tools = [], string $model = null): string
    {
        $result = $this->chatThroughRuntime($prompt, $messages, $tools, 'openai', $model);

        return (string) ($result['content'] ?? '');
    }

    public function generateOpenAIWithUsage(string $prompt, array $messages = [], array $tools = [], string $model = null): array
    {
        $result = $this->chatThroughRuntime($prompt, $messages, $tools, 'openai', $model);

        return [
            'reply' => (string) ($result['content'] ?? ''),
            'prompt_tokens' => (int) ($result['usage']['prompt_tokens'] ?? 0),
            'completion_tokens' => (int) ($result['usage']['completion_tokens'] ?? 0),
            'total_tokens' => (int) ($result['usage']['total_tokens'] ?? 0),
        ];
    }

    public function generateAnthropic(string $prompt, array $messages = [], array $tools = [], string $model = null): string
    {
        $result = $this->chatThroughRuntime($prompt, $messages, $tools, 'anthropic', $model);

        return (string) ($result['content'] ?? '');
    }

    public function uploadFile(string $filePath): string
    {
        return $filePath;
    }

    public function createVectorStore(string $name, string $fileId): stdClass
    {
        $result = new stdClass();
        $result->id = $fileId ?: 'vector-store';
        $result->name = $name;

        return $result;
    }

    public function generate(string $prompt, array $messages = [], array $tools = [], string $provider = null, string $model = null): string
    {
        $provider = $this->normalizeChatProvider($provider ?? config('titancore.ai.default_provider', 'openai'));

        return (string) ($this->chatThroughRuntime($prompt, $messages, $tools, $provider, $model)['content'] ?? '');
    }

    public function generateGemini(string $prompt, array $messages = [], array $tools = [], string $model = null): string
    {
        return $this->generate($prompt, $messages, $tools, 'openai', $model);
    }

    public function generateOllama(string $prompt, array $messages = [], array $tools = [], string $model = null): string
    {
        return $this->generate($prompt, $messages, $tools, 'local', $model);
    }

    public function generateAzureOpenAI(string $prompt, array $messages = [], array $tools = [], string $model = null): string
    {
        return $this->generate($prompt, $messages, $tools, 'openai', $model);
    }

    public function generateOpenRouter(string $prompt, array $messages = [], array $tools = [], string $model = null): string
    {
        return $this->generate($prompt, $messages, $tools, 'openai', $model);
    }

    public function generateGroq(string $prompt, array $messages = [], array $tools = [], string $model = null): string
    {
        return $this->generate($prompt, $messages, $tools, 'openai', $model);
    }

    public function generateMistral(string $prompt, array $messages = [], array $tools = [], string $model = null): string
    {
        return $this->generate($prompt, $messages, $tools, 'openai', $model);
    }

    public function textToSpeechElevenLabs(string $text, ?string $voiceId = null): ?string
    {
        return null;
    }

    private function chatThroughRuntime(string $prompt, array $messages, array $tools, string $provider, ?string $model): array
    {
        $payloadMessages = array_merge($messages, [[
            'role' => 'user',
            'content' => $prompt,
        ]]);

        return $this->gateway->chat($payloadMessages, [], array_filter([
            'provider' => $this->normalizeChatProvider($provider),
            'model' => $model,
            'tools' => $tools,
            'tool_choice' => !empty($tools) ? 'auto' : null,
        ], static fn ($value) => $value !== null));
    }

    private function normalizeChatProvider(?string $provider): string
    {
        return match ($provider) {
            'local', 'ollama' => 'local',
            default => 'openai',
        };
    }

    private function normalizeEmbeddingProvider(?string $provider): string
    {
        return $this->normalizeChatProvider($provider);
    }
}
