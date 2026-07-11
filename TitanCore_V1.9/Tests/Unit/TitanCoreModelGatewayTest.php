<?php

namespace Modules\TitanCore\Tests\Unit;

use Illuminate\Contracts\Container\Container;
use Modules\TitanCore\AI\Providers\LocalModelProvider;
use Modules\TitanCore\AI\Providers\NullChatProvider;
use Modules\TitanCore\Contracts\AI\ChatProviderContract;
use Modules\TitanCore\Contracts\AI\EmbeddingProviderContract;
use Modules\TitanCore\Providers\TitanCoreServiceProvider;
use Modules\TitanCore\Services\TitanCoreModelGateway;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class TitanCoreModelGatewayTest extends TestCase
{
    public function test_chat_resolves_providers_through_the_container(): void
    {
        $container = $this->makeContainerStub([
            NullChatProvider::class => new class implements ChatProviderContract {
                public function chat(array $messages, array $options = []): array
                {
                    return [
                        'ok' => true,
                        'content' => 'container chat',
                        'usage' => null,
                        'model' => 'stub',
                        'latency_ms' => 1,
                        'provider' => 'container',
                        'error' => null,
                    ];
                }

                public function health(): array
                {
                    return ['ok' => true, 'provider' => 'container', 'reason' => null];
                }

                public function providerName(): string
                {
                    return 'container';
                }
            },
        ]);

        $gateway = new TitanCoreModelGateway(null, $container);
        $result = $gateway->chat([['role' => 'user', 'content' => 'hello']], [], ['provider' => 'null']);

        $this->assertSame('container chat', $result['content']);
        $this->assertSame([NullChatProvider::class], $container->resolved);
    }

    public function test_embed_resolves_local_provider_through_the_container(): void
    {
        $container = $this->makeContainerStub([
            LocalModelProvider::class => new class implements ChatProviderContract, EmbeddingProviderContract {
                public function chat(array $messages, array $options = []): array
                {
                    return [
                        'ok' => true,
                        'content' => 'local chat',
                        'usage' => null,
                        'model' => 'stub',
                        'latency_ms' => 1,
                        'provider' => 'local',
                        'error' => null,
                    ];
                }

                public function embed(string|array $input, array $options = []): array
                {
                    return [
                        'ok' => true,
                        'vectors' => [[0.1, 0.2, 0.3]],
                        'usage' => null,
                        'model' => 'stub',
                        'latency_ms' => 1,
                        'provider' => 'local',
                        'error' => null,
                    ];
                }

                public function health(): array
                {
                    return ['ok' => true, 'provider' => 'local', 'reason' => null];
                }

                public function providerName(): string
                {
                    return 'local';
                }
            },
        ]);

        $gateway = new TitanCoreModelGateway(null, $container);
        $result = $gateway->embed('hello', [], ['provider' => 'local']);

        $this->assertSame([[0.1, 0.2, 0.3]], $result['vectors']);
        $this->assertSame([LocalModelProvider::class], $container->resolved);
    }

    public function test_validate_titan_config_uses_the_underscored_runtime_key(): void
    {
        $originalConfig = $GLOBALS['__titan_config'] ?? [];

        $GLOBALS['__titan_config'] = [
            'titan_model_runtime' => [
                'providers' => [
                    'openai' => ['api_key' => 'test'],
                ],
            ],
            'titan-ai' => [
                'default_provider' => 'openai',
            ],
            'titan-modules' => [
                'path' => 'Modules',
            ],
        ];

        try {
            $provider = new TitanCoreServiceProvider($this->makeContainerStub([]));
            $method = new ReflectionMethod(TitanCoreServiceProvider::class, 'validateTitanConfig');
            $method->setAccessible(true);
            $method->invoke($provider);

            $this->assertTrue(true);
        } finally {
            $GLOBALS['__titan_config'] = $originalConfig;
        }
    }

    /**
     * @param  array<class-string, object>  $providers
     */
    private function makeContainerStub(array $providers): Container
    {
        return new class($providers) implements Container {
            public array $resolved = [];

            /**
             * @param  array<class-string, object>  $providers
             */
            public function __construct(private array $providers) {}

            public function make(string $abstract, array $parameters = []): mixed
            {
                $this->resolved[] = $abstract;

                if (! array_key_exists($abstract, $this->providers)) {
                    throw new \RuntimeException("Unexpected resolution: {$abstract}");
                }

                return $this->providers[$abstract];
            }

            public function bind(...$args): void {}
            public function singleton(...$args): void {}
            public function instance(...$args): void {}
            public function alias(...$args): void {}
            public function tagged(...$args): array { return []; }
            public function tag(...$args): void {}
            public function has(...$args): bool { return false; }
            public function bound(...$args): bool { return false; }
            public function resolved(...$args): bool { return false; }
            public function makeWith(...$args): mixed { return $this->make($args[0], $args[1] ?? []); }
            public function extend(...$args): void {}
            public function __call(string $name, array $arguments): mixed
            {
                return null;
            }
        };
    }
}
