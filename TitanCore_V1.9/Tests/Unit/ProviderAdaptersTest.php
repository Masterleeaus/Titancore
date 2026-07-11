<?php

namespace Modules\TitanCore\Tests\Unit;

use Illuminate\Support\Facades\Http;
use Modules\TitanCore\AI\Providers\OpenAiChatProvider;
use Modules\TitanCore\AI\Providers\OpenAiEmbeddingProvider;
use Modules\TitanCore\AI\Providers\LocalModelProvider;
use Modules\TitanCore\Services\ProviderFailoverChain;
use Modules\TitanCore\Services\TitanCoreModelGateway;
use PHPUnit\Framework\TestCase;

class ProviderAdaptersTest extends TestCase
{
    // -------------------------------------------------------------------------
    // OpenAiChatProvider
    // -------------------------------------------------------------------------

    public function test_openai_chat_provider_returns_error_without_api_key(): void
    {
        $provider = new OpenAiChatProvider(apiKey: '');
        $result   = $provider->chat([['role' => 'user', 'content' => 'hello']]);

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('OPENAI_API_KEY', $result['error']);
        $this->assertSame('openai', $result['provider']);
    }

    public function test_openai_chat_provider_health_without_key(): void
    {
        $provider = new OpenAiChatProvider(apiKey: '');
        $health   = $provider->health();

        $this->assertFalse($health['ok']);
        $this->assertSame('openai', $health['provider']);
        $this->assertNotNull($health['reason']);
    }

    public function test_openai_chat_provider_name(): void
    {
        $provider = new OpenAiChatProvider(apiKey: 'sk-test');
        $this->assertSame('openai', $provider->providerName());
    }

    // -------------------------------------------------------------------------
    // OpenAiEmbeddingProvider
    // -------------------------------------------------------------------------

    public function test_openai_embedding_provider_returns_error_without_api_key(): void
    {
        $provider = new OpenAiEmbeddingProvider(apiKey: '');
        $result   = $provider->embed('hello world');

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('OPENAI_API_KEY', $result['error']);
        $this->assertSame('openai', $result['provider']);
    }

    public function test_openai_embedding_provider_health_without_key(): void
    {
        $provider = new OpenAiEmbeddingProvider(apiKey: '');
        $health   = $provider->health();

        $this->assertFalse($health['ok']);
        $this->assertSame('openai', $health['provider']);
    }

    public function test_openai_embedding_provider_name(): void
    {
        $provider = new OpenAiEmbeddingProvider(apiKey: 'sk-test');
        $this->assertSame('openai', $provider->providerName());
    }

    // -------------------------------------------------------------------------
    // LocalModelProvider
    // -------------------------------------------------------------------------

    public function test_local_model_provider_name(): void
    {
        $provider = new LocalModelProvider(baseUrl: 'http://localhost:11434');
        $this->assertSame('local', $provider->providerName());
    }

    public function test_local_model_provider_health_without_base_url(): void
    {
        $provider = new LocalModelProvider(baseUrl: '');
        $health   = $provider->health();

        $this->assertFalse($health['ok']);
        $this->assertSame('local', $health['provider']);
    }

    // -------------------------------------------------------------------------
    // ProviderFailoverChain
    // -------------------------------------------------------------------------

    public function test_failover_chain_returns_no_providers_error_when_empty(): void
    {
        $chain  = new ProviderFailoverChain([]);
        $result = $chain->chat([['role' => 'user', 'content' => 'hi']]);

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('No chat providers', $result['error']);
    }

    public function test_failover_chain_returns_first_ok_result(): void
    {
        $stub1 = $this->createStub(\Modules\TitanCore\Contracts\AI\ChatProviderContract::class);
        $stub1->method('chat')->willReturn([
            'ok'         => true,
            'content'    => 'from provider 1',
            'usage'      => null,
            'model'      => 'gpt-4o-mini',
            'latency_ms' => 100,
            'provider'   => 'openai',
            'error'      => null,
        ]);
        $stub1->method('providerName')->willReturn('openai');

        $chain  = new ProviderFailoverChain([$stub1]);
        $result = $chain->chat([['role' => 'user', 'content' => 'hi']]);

        $this->assertTrue($result['ok']);
        $this->assertSame('from provider 1', $result['content']);
    }

    public function test_failover_chain_falls_over_on_429(): void
    {
        $failing = $this->createStub(\Modules\TitanCore\Contracts\AI\ChatProviderContract::class);
        $failing->method('chat')->willReturn([
            'ok'       => false,
            'content'  => null,
            'usage'    => null,
            'model'    => null,
            'latency_ms' => 50,
            'provider' => 'openai',
            'error'    => 'rate limited',
            'status'   => 429,
        ]);
        $failing->method('providerName')->willReturn('openai');

        $backup = $this->createStub(\Modules\TitanCore\Contracts\AI\ChatProviderContract::class);
        $backup->method('chat')->willReturn([
            'ok'         => true,
            'content'    => 'from backup',
            'usage'      => null,
            'model'      => 'llama3',
            'latency_ms' => 200,
            'provider'   => 'local',
            'error'      => null,
        ]);
        $backup->method('providerName')->willReturn('local');

        $chain  = new ProviderFailoverChain([$failing, $backup]);
        $result = $chain->chat([['role' => 'user', 'content' => 'hi']]);

        $this->assertTrue($result['ok']);
        $this->assertSame('from backup', $result['content']);
    }

    public function test_failover_chain_does_not_fail_over_on_400(): void
    {
        $failing = $this->createStub(\Modules\TitanCore\Contracts\AI\ChatProviderContract::class);
        $failing->method('chat')->willReturn([
            'ok'       => false,
            'content'  => null,
            'usage'    => null,
            'model'    => null,
            'latency_ms' => 50,
            'provider' => 'openai',
            'error'    => 'bad request',
            'status'   => 400,
        ]);
        $failing->method('providerName')->willReturn('openai');

        $backup = $this->createStub(\Modules\TitanCore\Contracts\AI\ChatProviderContract::class);
        // backup should never be called for a 400
        $backup->expects($this->never())->method('chat');
        $backup->method('providerName')->willReturn('local');

        $chain  = new ProviderFailoverChain([$failing, $backup]);
        $result = $chain->chat([['role' => 'user', 'content' => 'hi']]);

        $this->assertFalse($result['ok']);
        $this->assertSame('bad request', $result['error']);
    }

    public function test_failover_chain_embed_falls_over_on_500(): void
    {
        $failing = $this->createStub(\Modules\TitanCore\Contracts\AI\EmbeddingProviderContract::class);
        $failing->method('embed')->willReturn([
            'ok'         => false,
            'vectors'    => null,
            'usage'      => null,
            'model'      => null,
            'latency_ms' => 50,
            'provider'   => 'openai',
            'error'      => 'server error',
            'status'     => 500,
        ]);
        $failing->method('providerName')->willReturn('openai');

        $backup = $this->createStub(\Modules\TitanCore\Contracts\AI\EmbeddingProviderContract::class);
        $backup->method('embed')->willReturn([
            'ok'         => true,
            'vectors'    => [[0.1, 0.2]],
            'usage'      => ['prompt_tokens' => 3, 'total_tokens' => 3],
            'model'      => 'nomic-embed-text',
            'latency_ms' => 300,
            'provider'   => 'local',
            'error'      => null,
        ]);
        $backup->method('providerName')->willReturn('local');

        $chain  = new ProviderFailoverChain([$failing, $backup]);
        $result = $chain->embed('hello world');

        $this->assertTrue($result['ok']);
        $this->assertNotEmpty($result['vectors']);
    }

    public function test_failover_chain_health_delegates_to_primary(): void
    {
        $primary = $this->createStub(\Modules\TitanCore\Contracts\AI\ChatProviderContract::class);
        $primary->method('health')->willReturn(['ok' => true, 'provider' => 'openai', 'reason' => null]);
        $primary->method('providerName')->willReturn('openai');

        $chain  = new ProviderFailoverChain([$primary]);
        $health = $chain->health();

        $this->assertTrue($health['ok']);
        $this->assertStringContainsString('openai', $health['provider']);
    }

    // -------------------------------------------------------------------------
    // TitanCoreModelGateway (unit — no real HTTP)
    // -------------------------------------------------------------------------

    public function test_gateway_chat_returns_error_without_api_key(): void
    {
        // Gateway with no OPENAI_API_KEY should return ok=false gracefully.
        $gateway = new TitanCoreModelGateway();
        $result  = $gateway->chat(
            [['role' => 'user', 'content' => 'hello']],
            [],
            ['provider' => 'openai']
        );

        $this->assertArrayHasKey('ok', $result);
        $this->assertArrayHasKey('provider', $result);
        // Without an API key the provider returns ok=false
        $this->assertFalse($result['ok']);
    }

    public function test_gateway_embed_returns_error_without_api_key(): void
    {
        $gateway = new TitanCoreModelGateway();
        $result  = $gateway->embed('hello', [], ['provider' => 'openai']);

        $this->assertArrayHasKey('ok', $result);
        $this->assertFalse($result['ok']);
    }
}
