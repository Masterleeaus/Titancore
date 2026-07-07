<?php

namespace Modules\TitanCore\Tests\Unit;

use Modules\TitanCore\Services\TitanCoreAIService;
use Modules\TitanCore\Services\TitanCoreModelGateway;
use PHPUnit\Framework\TestCase;

class TitanCoreAIServiceTest extends TestCase
{
    public function test_generate_openai_with_usage_uses_runtime_gateway(): void
    {
        $gateway = new class extends TitanCoreModelGateway {
            public array $calls = [];

            public function chat(array $messages, array $context = [], array $options = []): array
            {
                $this->calls[] = compact('messages', 'context', 'options');

                return [
                    'ok' => true,
                    'content' => 'runtime response',
                    'usage' => [
                        'prompt_tokens' => 10,
                        'completion_tokens' => 5,
                        'total_tokens' => 15,
                    ],
                    'provider' => $options['provider'] ?? 'openai',
                    'model' => $options['model'] ?? 'gpt-4o-mini',
                ];
            }
        };

        $service = new TitanCoreAIService($gateway);
        $result = $service->generateOpenAIWithUsage('hello', [['role' => 'system', 'content' => 'sys']], [], 'gpt-test');

        $this->assertSame('runtime response', $result['reply']);
        $this->assertSame(10, $result['prompt_tokens']);
        $this->assertSame(5, $result['completion_tokens']);
        $this->assertSame(15, $result['total_tokens']);
    }

    public function test_generate_ollama_routes_to_local_provider(): void
    {
        $gateway = new class extends TitanCoreModelGateway {
            public array $calls = [];

            public function chat(array $messages, array $context = [], array $options = []): array
            {
                $this->calls[] = compact('messages', 'context', 'options');

                return [
                    'ok' => true,
                    'content' => 'local response',
                    'usage' => null,
                    'provider' => $options['provider'] ?? 'local',
                    'model' => $options['model'] ?? null,
                ];
            }
        };

        $service = new TitanCoreAIService($gateway);
        $response = $service->generateOllama('prompt', [], [], 'llama-test');

        $this->assertSame('local response', $response);
    }
}
