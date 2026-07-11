<?php

namespace Modules\TitanCore\Tests\Unit;

use Modules\TitanCore\Services\AssetDiscoveryService;
use Modules\TitanCore\Support\AssetManifestValidator;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for provider discovery via AssetDiscoveryService.
 *
 * Covers:
 *  - Discovering all providers declared in provider.json.
 *  - Correct extraction of provider capabilities, models, auth, cost_tracking, failover.
 *  - discoverProviders() convenience method.
 *  - Missing, invalid, or empty provider.json handled gracefully.
 *  - Null / fallback providers are discoverable alongside real providers.
 *  - Real TitanCore provider.json produces expected provider entries.
 */
class ProviderDiscoveryTest extends TestCase
{
    private string $tmpDir;
    private AssetDiscoveryService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tmpDir  = sys_get_temp_dir() . '/titan_provider_discovery_test_' . uniqid('', true);
        mkdir($this->tmpDir, 0755, true);

        $this->service = new AssetDiscoveryService(new AssetManifestValidator());
    }

    protected function tearDown(): void
    {
        $this->rimraf($this->tmpDir);
        parent::tearDown();
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function rimraf(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir . '/' . $item;
            is_dir($path) ? $this->rimraf($path) : unlink($path);
        }

        rmdir($dir);
    }

    private function writeProviderJson(array $providers, array $extra = []): void
    {
        $dir = $this->tmpDir . '/Providers';

        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $manifest = array_merge([
            'name'         => 'TestProviders',
            'version'      => '1.0.0',
            'description'  => 'Test provider registry',
            'module'       => 'TestModule',
            'capabilities' => ['chat'],
            'providers'    => $providers,
        ], $extra);

        file_put_contents($dir . '/provider.json', json_encode($manifest, JSON_PRETTY_PRINT));
    }

    private function providerItem(array $overrides = []): array
    {
        return array_merge([
            'id'             => 'test_provider',
            'name'           => 'Test Provider',
            'description'    => 'A test AI provider',
            'capabilities'   => ['chat', 'streaming'],
            'models'         => ['test-model-v1'],
            'authentication' => ['type' => 'bearer_token', 'env_key' => 'TEST_API_KEY'],
            'rate_limits'    => ['requests_per_minute' => 1000],
            'cost_tracking'  => ['enabled' => true],
            'failover'       => ['supported' => true, 'fallback_provider' => 'null_chat'],
            'health_check'   => true,
        ], $overrides);
    }

    // ── Tests: basic provider discovery ──────────────────────────────────────

    public function test_discovers_single_provider(): void
    {
        $this->writeProviderJson([$this->providerItem()]);

        $providers = $this->service->discoverProviders($this->tmpDir);

        $this->assertCount(1, $providers);
        $this->assertSame('test_provider', $providers[0]['id']);
        $this->assertSame('Test Provider', $providers[0]['name']);
    }

    public function test_discovers_multiple_providers(): void
    {
        $this->writeProviderJson([
            $this->providerItem(['id' => 'provider_a', 'name' => 'Provider A']),
            $this->providerItem(['id' => 'provider_b', 'name' => 'Provider B']),
            $this->providerItem(['id' => 'provider_c', 'name' => 'Provider C', 'capabilities' => ['embeddings']]),
        ]);

        $providers = $this->service->discoverProviders($this->tmpDir);

        $this->assertCount(3, $providers);
        $ids = array_column($providers, 'id');
        $this->assertContains('provider_a', $ids);
        $this->assertContains('provider_b', $ids);
        $this->assertContains('provider_c', $ids);
    }

    // ── Tests: field extraction ───────────────────────────────────────────────

    public function test_provider_capabilities_are_extracted_correctly(): void
    {
        $this->writeProviderJson([
            $this->providerItem(['capabilities' => ['chat', 'streaming', 'function-calling']]),
        ]);

        $providers = $this->service->discoverProviders($this->tmpDir);

        $this->assertContains('chat', $providers[0]['capabilities']);
        $this->assertContains('streaming', $providers[0]['capabilities']);
        $this->assertContains('function-calling', $providers[0]['capabilities']);
    }

    public function test_provider_models_are_extracted_correctly(): void
    {
        $this->writeProviderJson([
            $this->providerItem(['models' => ['gpt-4o', 'gpt-4o-mini', 'gpt-3.5-turbo']]),
        ]);

        $providers = $this->service->discoverProviders($this->tmpDir);

        $this->assertSame(['gpt-4o', 'gpt-4o-mini', 'gpt-3.5-turbo'], $providers[0]['models']);
    }

    public function test_provider_authentication_block_is_extracted(): void
    {
        $auth = ['type' => 'bearer_token', 'env_key' => 'OPENAI_API_KEY', 'config_path' => 'ai.providers.openai.api_key'];
        $this->writeProviderJson([
            $this->providerItem(['authentication' => $auth]),
        ]);

        $providers = $this->service->discoverProviders($this->tmpDir);

        $this->assertSame($auth, $providers[0]['authentication']);
    }

    public function test_provider_cost_tracking_is_extracted(): void
    {
        $this->writeProviderJson([
            $this->providerItem(['cost_tracking' => ['enabled' => true, 'pricing_config' => 'ai.pricing']]),
        ]);

        $providers = $this->service->discoverProviders($this->tmpDir);

        $this->assertTrue($providers[0]['cost_tracking']['enabled']);
        $this->assertSame('ai.pricing', $providers[0]['cost_tracking']['pricing_config']);
    }

    public function test_provider_failover_config_is_extracted(): void
    {
        $failover = ['supported' => true, 'fallback_provider' => 'null_chat'];
        $this->writeProviderJson([
            $this->providerItem(['failover' => $failover]),
        ]);

        $providers = $this->service->discoverProviders($this->tmpDir);

        $this->assertSame($failover, $providers[0]['failover']);
    }

    public function test_provider_health_check_flag_is_extracted(): void
    {
        $this->writeProviderJson([$this->providerItem(['health_check' => true])]);
        $providers = $this->service->discoverProviders($this->tmpDir);
        $this->assertTrue($providers[0]['health_check']);

        // false case
        $this->writeProviderJson([$this->providerItem(['health_check' => false])]);
        $providers = $this->service->discoverProviders($this->tmpDir);
        $this->assertFalse($providers[0]['health_check']);
    }

    // ── Tests: null / fallback providers ─────────────────────────────────────

    public function test_null_chat_provider_is_discoverable(): void
    {
        $this->writeProviderJson([
            $this->providerItem(['id' => 'openai_chat', 'name' => 'OpenAI Chat', 'health_check' => true]),
            $this->providerItem([
                'id'             => 'null_chat',
                'name'           => 'Null Chat Provider',
                'capabilities'   => ['chat'],
                'models'         => ['null'],
                'authentication' => ['type' => 'none'],
                'health_check'   => false,
                'cost_tracking'  => ['enabled' => false],
                'failover'       => ['supported' => false],
            ]),
        ]);

        $providers = $this->service->discoverProviders($this->tmpDir);
        $ids       = array_column($providers, 'id');

        $this->assertContains('null_chat', $ids);
    }

    public function test_null_embedding_provider_is_discoverable(): void
    {
        $this->writeProviderJson([
            $this->providerItem([
                'id'           => 'null_embedding',
                'name'         => 'Null Embedding Provider',
                'capabilities' => ['embeddings'],
                'models'       => ['null'],
                'authentication' => ['type' => 'none'],
                'health_check' => false,
                'cost_tracking' => ['enabled' => false],
                'failover'      => ['supported' => false],
            ]),
        ]);

        $providers = $this->service->discoverProviders($this->tmpDir);
        $ids       = array_column($providers, 'id');

        $this->assertContains('null_embedding', $ids);
    }

    // ── Tests: error handling ─────────────────────────────────────────────────

    public function test_missing_provider_json_returns_empty_array(): void
    {
        $providers = $this->service->discoverProviders($this->tmpDir);

        $this->assertEmpty($providers);
    }

    public function test_invalid_json_returns_empty_array(): void
    {
        $dir = $this->tmpDir . '/Providers';
        mkdir($dir, 0755, true);
        file_put_contents($dir . '/provider.json', '{broken json}');

        $providers = $this->service->discoverProviders($this->tmpDir);

        $this->assertEmpty($providers);
    }

    public function test_provider_json_missing_required_fields_returns_empty(): void
    {
        $dir = $this->tmpDir . '/Providers';
        mkdir($dir, 0755, true);
        // Missing 'providers' key
        file_put_contents($dir . '/provider.json', json_encode([
            'name'    => 'X',
            'version' => '1.0.0',
        ]));

        $providers = $this->service->discoverProviders($this->tmpDir);

        $this->assertEmpty($providers);
    }

    public function test_provider_json_with_empty_providers_array_returns_empty(): void
    {
        $this->writeProviderJson([]);

        $providers = $this->service->discoverProviders($this->tmpDir);

        $this->assertEmpty($providers);
    }

    public function test_non_array_provider_items_are_filtered_out(): void
    {
        $dir = $this->tmpDir . '/Providers';
        mkdir($dir, 0755, true);

        // Mix a valid provider with invalid (string) entries
        $manifest = [
            'name'         => 'P',
            'version'      => '1.0.0',
            'description'  => 'd',
            'module'       => 'M',
            'capabilities' => ['chat'],
            'providers'    => [
                $this->providerItem(),   // valid
                'not-an-array',          // invalid — should be skipped
                42,                      // invalid — should be skipped
            ],
        ];
        file_put_contents($dir . '/provider.json', json_encode($manifest));

        $providers = $this->service->discoverProviders($this->tmpDir);

        $this->assertCount(1, $providers);
        $this->assertSame('test_provider', $providers[0]['id']);
    }

    // ── Tests: real TitanCore provider.json ──────────────────────────────────

    public function test_real_provider_json_discovers_openai_chat(): void
    {
        $realAiDir = __DIR__ . '/../../AI';

        if (! is_dir($realAiDir)) {
            $this->markTestSkipped('AI/ directory not found next to Tests/.');
        }

        $providers = $this->service->discoverProviders($realAiDir);
        $ids       = array_column($providers, 'id');

        $this->assertContains('openai_chat', $ids, 'Expected openai_chat in real provider.json');
    }

    public function test_real_provider_json_discovers_anthropic(): void
    {
        $realAiDir = __DIR__ . '/../../AI';

        if (! is_dir($realAiDir)) {
            $this->markTestSkipped('AI/ directory not found next to Tests/.');
        }

        $providers = $this->service->discoverProviders($realAiDir);
        $ids       = array_column($providers, 'id');

        $this->assertContains('anthropic_chat', $ids, 'Expected anthropic_chat in real provider.json');
    }

    public function test_real_provider_json_discovers_null_providers(): void
    {
        $realAiDir = __DIR__ . '/../../AI';

        if (! is_dir($realAiDir)) {
            $this->markTestSkipped('AI/ directory not found next to Tests/.');
        }

        $providers = $this->service->discoverProviders($realAiDir);
        $ids       = array_column($providers, 'id');

        $this->assertContains('null_chat', $ids, 'Expected null_chat fallback provider in real provider.json');
        $this->assertContains('null_embedding', $ids, 'Expected null_embedding fallback provider in real provider.json');
    }

    public function test_real_provider_json_discovers_local_model(): void
    {
        $realAiDir = __DIR__ . '/../../AI';

        if (! is_dir($realAiDir)) {
            $this->markTestSkipped('AI/ directory not found next to Tests/.');
        }

        $providers = $this->service->discoverProviders($realAiDir);
        $ids       = array_column($providers, 'id');

        $this->assertContains('local_model', $ids, 'Expected local_model (Ollama/LM Studio) in real provider.json');
    }

    public function test_real_providers_all_have_required_fields(): void
    {
        $realAiDir = __DIR__ . '/../../AI';

        if (! is_dir($realAiDir)) {
            $this->markTestSkipped('AI/ directory not found next to Tests/.');
        }

        $providers = $this->service->discoverProviders($realAiDir);
        $this->assertNotEmpty($providers);

        foreach ($providers as $provider) {
            $this->assertArrayHasKey('id', $provider, "Provider missing 'id': " . json_encode($provider));
            $this->assertArrayHasKey('name', $provider, "Provider missing 'name': " . ($provider['id'] ?? '?'));
            $this->assertArrayHasKey('description', $provider, "Provider '{$provider['id']}' missing 'description'");
            $this->assertArrayHasKey('capabilities', $provider, "Provider '{$provider['id']}' missing 'capabilities'");
            $this->assertIsArray($provider['capabilities']);
        }
    }

    public function test_real_openai_chat_provider_declares_chat_capability(): void
    {
        $realAiDir = __DIR__ . '/../../AI';

        if (! is_dir($realAiDir)) {
            $this->markTestSkipped('AI/ directory not found next to Tests/.');
        }

        $providers   = $this->service->discoverProviders($realAiDir);
        $openaiChat  = null;

        foreach ($providers as $p) {
            if (($p['id'] ?? '') === 'openai_chat') {
                $openaiChat = $p;
                break;
            }
        }

        $this->assertNotNull($openaiChat, 'openai_chat provider not found.');
        $this->assertContains('chat', $openaiChat['capabilities']);
    }

    public function test_real_openai_embedding_provider_declares_embeddings_capability(): void
    {
        $realAiDir = __DIR__ . '/../../AI';

        if (! is_dir($realAiDir)) {
            $this->markTestSkipped('AI/ directory not found next to Tests/.');
        }

        $providers = $this->service->discoverProviders($realAiDir);
        $embed     = null;

        foreach ($providers as $p) {
            if (($p['id'] ?? '') === 'openai_embedding') {
                $embed = $p;
                break;
            }
        }

        $this->assertNotNull($embed, 'openai_embedding provider not found.');
        $this->assertContains('embeddings', $embed['capabilities']);
    }
}
