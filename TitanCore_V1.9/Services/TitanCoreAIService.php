<?php

namespace Modules\TitanCore\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use stdClass;

class TitanCoreAIService
{
    // Placeholder for centralized AI API key management
    private function resolveApiKey(string $provider): string
    {
        // In a real implementation, this would fetch the API key securely
        // from a central configuration or secret management system.
        return match ($provider) {
            'openai' => (string) (config('titancore.ai.openai.key') ?? env('OPENAI_API_KEY', '')),
            'anthropic' => (string) (config('titancore.ai.anthropic.key') ?? env('ANTHROPIC_API_KEY', '')),
            'gemini' => (string) (config('titancore.ai.gemini.key') ?? env('GEMINI_API_KEY', '')),
            'ollama' => (string) (config('titancore.ai.ollama.key') ?? env('OLLAMA_API_KEY', '')),
            'azure_openai' => (string) (config('titancore.ai.azure_openai.key') ?? env('AZURE_OPENAI_API_KEY', '')),
            'openrouter' => (string) (config('titancore.ai.openrouter.key') ?? env('OPENROUTER_API_KEY', '')),
            'groq' => (string) (config('titancore.ai.groq.key') ?? env('GROQ_API_KEY', '')),
            'mistral' => (string) (config('titancore.ai.mistral.key') ?? env('MISTRAL_API_KEY', '')),
            'elevenlabs' => (string) (config('titancore.ai.elevenlabs.key') ?? env('ELEVENLABS_API_KEY', '')),
            default => '',
        };
    }

    public function isProviderAvailable(string $provider): bool
    {
        return $this->resolveApiKey($provider) !== '';
    }

    public function embed(string $text, string $model): array
    {
        return $this->embedBatch([$text], $model)[0] ?? [];
    }

    public function embedBatch(array $texts, string $model): array
    {
        $apiKey = $this->resolveApiKey('openai'); // Assuming OpenAI for embeddings
        if (empty($apiKey)) {
            Log::warning('TitanCoreAIService: OpenAI API key not configured for embeddings.');
            return array_fill(0, count($texts), []);
        }

        try {
            $response = Http::withToken($apiKey)
                ->timeout(20)
                ->post('https://api.openai.com/v1/embeddings', [
                    'model' => $model,
                    'input' => $texts,
                ]);

            if ($response->failed()) {
                Log::warning('TitanCoreAIService: OpenAI embedding request failed.', ['status' => $response->status()]);
                return array_fill(0, count($texts), []);
            }

            $byIndex = [];
            foreach ((array) $response->json('data', []) as $row) {
                $index = (int) ($row['index'] ?? 0);
                $byIndex[$index] = (array) ($row['embedding'] ?? []);
            }

            $vectors = [];
            for ($index = 0; $index < count($texts); $index++) {
                $vectors[] = $byIndex[$index] ?? [];
            }

            return $vectors;
        } catch (\Throwable $e) {
            Log::error('TitanCoreAIService: Exception during OpenAI embedding.', ['error' => $e->getMessage()]);
            return array_fill(0, count($texts), []);
        }
    }

    public function generateOpenAI(string $prompt, array $messages = [], array $tools = [], string $model = null): string
    {
        $apiKey = $this->resolveApiKey('openai');
        $model  = $model ?? config('titancore.ai.openai.model', 'gpt-4o-mini');
        $maxTokens = (int) config('titancore.ai.openai.max_tokens', 4096);

        if (empty($apiKey)) {
            Log::warning('TitanCoreAIService: OpenAI API key not configured for generation.');
            return '';
        }

        $payload = [
            'model'      => $model,
            'max_tokens' => $maxTokens,
            'messages'   => array_merge($messages, [['role' => 'user', 'content' => $prompt]]),
        ];

        if (!empty($tools)) {
            $payload['tools']       = $tools;
            $payload['tool_choice'] = 'auto';
        }

        try {
            $response = Http::withToken($apiKey)
                ->post('https://api.openai.com/v1/chat/completions', $payload);

            if ($response->failed()) {
                Log::error('TitanCoreAIService: OpenAI generation request failed.', ['status' => $response->status()]);
                throw new \RuntimeException('OpenAI request failed with status ' . $response->status());
            }

            $choice = $response->json('choices.0') ?? [];
            if (!empty($tools) && isset($choice['message']['tool_calls'][0]['function']['arguments'])) {
                return $choice['message']['tool_calls'][0]['function']['arguments'];
            }

            return $response->json('choices.0.message.content', '');
        } catch (\Throwable $e) {
            Log::error('TitanCoreAIService: Exception during OpenAI generation.', ['error' => $e->getMessage()]);
            return '';
        }
    }

    public function generateOpenAIWithUsage(string $prompt, array $messages = [], array $tools = [], string $model = null): array
    {
        $apiKey = $this->resolveApiKey('openai');
        $model  = $model ?? config('titancore.ai.openai.model', 'gpt-4o-mini');
        $maxTokens = (int) config('titancore.ai.openai.max_tokens', 4096);

        if (empty($apiKey)) {
            Log::warning('TitanCoreAIService: OpenAI API key not configured for generation with usage.');
            return ['reply' => '', 'prompt_tokens' => 0, 'completion_tokens' => 0, 'total_tokens' => 0];
        }

        $payload = [
            'model'      => $model,
            'max_tokens' => $maxTokens,
            'messages'   => array_merge($messages, [['role' => 'user', 'content' => $prompt]]),
        ];

        if (!empty($tools)) {
            $payload['tools']       = $tools;
            $payload['tool_choice'] = 'auto';
        }

        try {
            $response = Http::withToken($apiKey)
                ->post('https://api.openai.com/v1/chat/completions', $payload);

            if ($response->failed()) {
                Log::error('TitanCoreAIService: OpenAI generation with usage request failed.', ['status' => $response->status()]);
                throw new \RuntimeException('OpenAI request failed with status ' . $response->status());
            }

            $json = $response->json();

            return [
                'reply'             => $json['choices'][0]['message']['content'] ?? '',
                'prompt_tokens'     => $json['usage']['prompt_tokens'] ?? 0,
                'completion_tokens' => $json['usage']['completion_tokens'] ?? 0,
                'total_tokens'      => $json['usage']['total_tokens'] ?? 0,
            ];
        } catch (\Throwable $e) {
            Log::error('TitanCoreAIService: Exception during OpenAI generation with usage.', ['error' => $e->getMessage()]);
            return ['reply' => '', 'prompt_tokens' => 0, 'completion_tokens' => 0, 'total_tokens' => 0];
        }
    }

    public function generateAnthropic(string $prompt, array $messages = [], array $tools = [], string $model = null): string
    {
        $apiKey = $this->resolveApiKey('anthropic');
        $model  = $model ?? config("titancore.ai.anthropic.model", "claude-3-haiku-20240307");
        $maxTokens = (int) config('titancore.ai.anthropic.max_tokens', 4096);

        if (empty($apiKey)) {
            Log::warning('TitanCoreAIService: Anthropic API key not configured for generation.');
            return '';
        }

        [$system, $history] = $this->extractSystemMessage($messages);

        $history[] = ['role' => 'user', 'content' => $prompt];

        $payload = [
            'model'      => $model,
            'max_tokens' => $maxTokens,
            'messages'   => $history,
        ];

        if ($system !== null) {
            $payload['system'] = $system;
        }

        if (!empty($tools)) {
            $payload['tools'] = $this->convertTools($tools);
        }

        try {
            $response = Http::withHeaders([
                'x-api-key'         => $apiKey,
                'anthropic-version' => '2023-06-01',
                'Content-Type'      => 'application/json',
                'Accept'            => 'application/json',
            ])->post('https://api.anthropic.com/v1/messages', $payload);

            if ($response->failed()) {
                Log::error('TitanCoreAIService: Anthropic generation request failed.', ['status' => $response->status()]);
                throw new \RuntimeException('Anthropic request failed with status ' . $response->status());
            }

            $json = $response->json();

            foreach ($json['content'] ?? [] as $block) {
                if (($block['type'] ?? '') === 'tool_use') {
                    return json_encode($block['input'] ?? []);
                }
            }

            foreach ($json['content'] ?? [] as $block) {
                if (($block['type'] ?? '') === 'text') {
                    return $block['text'];
                }
            }

            return '';
        } catch (\Throwable $e) {
            Log::error('TitanCoreAIService: Exception during Anthropic generation.', ['error' => $e->getMessage()]);
            return '';
        }
    }

    // Helper methods for Anthropic
    private function extractSystemMessage(array $messages): array
    {
        $system  = null;
        $history = [];

        foreach ($messages as $msg) {
            if (($msg['role'] ?? '') === 'system') {
                $system = (string) ($msg['content'] ?? '');
            } else {
                $history[] = $msg;
            }
        }

        return [$system, $history];
    }

    private function convertTools(array $tools): array
    {
        $converted = [];

        foreach ($tools as $tool) {
            if (($tool['type'] ?? '') === 'function' && isset($tool['function'])) {
                $fn = $tool['function'];
                $converted[] = [
                    'name'         => $fn['name'] ?? '',
                    'description'  => $fn['description'] ?? '',
                    'input_schema' => $fn['parameters'] ?? ['type' => 'object', 'properties' => []],
                ];
            }
        }

        return $converted;
    }

    // Placeholder for FileSearchService methods
    public function uploadFile(string $filePath): string
    {
        Log::info('TitanCoreAIService: Placeholder for uploadFile called.');
        // In a real implementation, this would interact with OpenAI's file upload API
        return 'file-placeholder-id';
    }

    public function createVectorStore(string $name, string $fileId): stdClass
    {
        Log::info("TitanCoreAIService: Placeholder for createVectorStore called.");
        // In a real implementation, this would interact with OpenAI's vector store API
        $result = new stdClass();
        $result->id = 'vs-placeholder-id';
        return $result;
    }

    public function generate(string $prompt, array $messages = [], array $tools = [], string $provider = null, string $model = null): string
    {
        $provider = $provider ?? config('titancore.ai.default_provider', 'openai');
        $model = $model ?? config("titancore.ai.{$provider}.model");

        return match ($provider) {
            'openai' => $this->generateOpenAI($prompt, $messages, $tools),
            'anthropic' => $this->generateAnthropic($prompt, $messages, $tools),
            'gemini' => $this->generateGemini($prompt, $messages, $tools, $model),
            'ollama' => $this->generateOllama($prompt, $messages, $tools, $model),
            'azure_openai' => $this->generateAzureOpenAI($prompt, $messages, $tools, $model),
            'openrouter' => $this->generateOpenRouter($prompt, $messages, $tools, $model),
            'groq' => $this->generateGroq($prompt, $messages, $tools, $model),
            'mistral' => $this->generateMistral($prompt, $messages, $tools, $model),
            default => throw new \InvalidArgumentException("Unsupported AI provider: {$provider}"),
        };
    }

    public function generateGemini(string $prompt, array $messages = [], array $tools = [], string $model = null): string
    {
        Log::info('TitanCoreAIService: Placeholder for generateGemini called.');
        return '';
    }

    public function generateOllama(string $prompt, array $messages = [], array $tools = [], string $model = null): string
    {
        Log::info('TitanCoreAIService: Placeholder for generateOllama called.');
        return '';
    }

    public function generateAzureOpenAI(string $prompt, array $messages = [], array $tools = [], string $model = null): string
    {
        Log::info('TitanCoreAIService: Placeholder for generateAzureOpenAI called.');
        return '';
    }

    public function generateOpenRouter(string $prompt, array $messages = [], array $tools = [], string $model = null): string
    {
        Log::info('TitanCoreAIService: Placeholder for generateOpenRouter called.');
        return '';
    }

    public function generateGroq(string $prompt, array $messages = [], array $tools = [], string $model = null): string
    {
        Log::info('TitanCoreAIService: Placeholder for generateGroq called.');
        return '';
    }

    public function generateMistral(string $prompt, array $messages = [], array $tools = [], string $model = null): string
    {
        Log::info('TitanCoreAIService: Placeholder for generateMistral called.');
        return '';
    }

    public function textToSpeechElevenLabs(string $text, ?string $voiceId = null): ?string
    {
        $apiKey = $this->resolveApiKey('elevenlabs');
        if (empty($apiKey) || empty($voiceId)) {
            Log::warning('TitanCoreAIService: ElevenLabs API key or voice ID not configured for TTS.');
            return null;
        }

        try {
            $response = Http::withHeaders([
                'xi-api-key' => $apiKey,
            ])->post("https://api.elevenlabs.io/v1/text-to-speech/{$voiceId}", [
                'text'     => $text,
                'model_id' => 'eleven_turbo_v2_5',
            ]);

            if ($response->failed()) {
                Log::error('TitanCoreAIService: ElevenLabs TTS request failed.', ['status' => $response->status()]);
                throw new \RuntimeException('ElevenLabs TTS request failed with status ' . $response->status());
            }

            return $response->body();
        } catch (\Throwable $e) {
            Log::error('TitanCoreAIService: Exception during ElevenLabs TTS.', ['error' => $e->getMessage()]);
            return null;
        }
    }

