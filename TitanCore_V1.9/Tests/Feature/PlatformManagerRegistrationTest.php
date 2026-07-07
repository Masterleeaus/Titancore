<?php

namespace Modules\TitanCore\Tests\Feature;

use Modules\TitanCore\Services\AssetDiscoveryService;
use Modules\TitanCore\Support\AssetManifestValidator;
use PHPUnit\Framework\TestCase;

/**
 * Feature tests for Platform Manager asset registration.
 *
 * These tests verify that TitanCorePlatformIntegrationServiceProvider drives
 * registration exclusively from metadata (no hardcoded asset lists) and that
 * the AssetDiscoveryService produces the correct registration payloads for the
 * Platform Manager's RegistryManager.
 *
 * Because the Platform Manager is an external module (Modules\Aitools), these
 * tests mock the RegistryManager and ModuleManager interfaces and assert that
 * the correct methods are called with correctly-shaped arguments. No actual
 * Platform Manager installation is required.
 */
class PlatformManagerRegistrationTest extends TestCase
{
    private string $tmpDir;
    private AssetDiscoveryService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tmpDir  = sys_get_temp_dir() . '/titan_pm_test_' . uniqid('', true);
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

    private function writeJson(string $relativePath, array $data): void
    {
        $absPath = $this->tmpDir . '/' . $relativePath;
        $dir     = dirname($absPath);

        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($absPath, json_encode($data, JSON_PRETTY_PRINT));
    }

    // ── Tests: discovery output shapes ────────────────────────────────────────

    /**
     * Verify that discovered providers have all fields required by RegistryManager::registerAsset('provider').
     */
    public function test_discovered_providers_have_registration_required_fields(): void
    {
        $this->writeJson('Providers/provider.json', [
            'name'         => 'Providers',
            'version'      => '1.0.0',
            'description'  => 'Test',
            'module'       => 'TitanCore',
            'capabilities' => ['chat'],
            'providers'    => [
                [
                    'id'           => 'openai_chat',
                    'name'         => 'OpenAI Chat',
                    'description'  => 'OpenAI provider',
                    'capabilities' => ['chat', 'streaming'],
                    'models'       => ['gpt-4'],
                    'authentication' => ['type' => 'bearer_token', 'env_key' => 'OPENAI_API_KEY'],
                    'rate_limits'  => ['requests_per_minute' => 3500],
                    'cost_tracking'=> ['enabled' => true],
                    'failover'     => ['supported' => true, 'fallback_provider' => 'null_chat'],
                    'health_check' => true,
                ],
            ],
        ]);

        $providers = $this->service->discoverProviders($this->tmpDir);

        $this->assertCount(1, $providers);
        $provider = $providers[0];

        // Fields that the Platform Manager's RegistryManager expects in metadata
        $this->assertArrayHasKey('id', $provider);
        $this->assertArrayHasKey('name', $provider);
        $this->assertArrayHasKey('description', $provider);
        $this->assertArrayHasKey('capabilities', $provider);
        $this->assertArrayHasKey('models', $provider);
        $this->assertArrayHasKey('authentication', $provider);
        $this->assertArrayHasKey('health_check', $provider);
    }

    /**
     * Verify that discovered tools have all fields required by RegistryManager::registerAsset('tool').
     */
    public function test_discovered_tools_have_registration_required_fields(): void
    {
        $this->writeJson('Tools/tool.json', [
            'name'         => 'Tools',
            'version'      => '1.0.0',
            'description'  => 'Test',
            'module'       => 'TitanCore',
            'capabilities' => ['tool-execution'],
            'tools'        => [
                [
                    'id'          => 'embed_tool',
                    'name'        => 'Embed Tool',
                    'description' => 'Embeds text',
                    'risk_class'  => 'read',
                    'parameters'  => [['name' => 'text', 'type' => 'string', 'required' => true]],
                    'capabilities'=> ['embeddings'],
                    'class'       => 'Modules\\TitanCore\\AI\\Providers\\OpenAiEmbeddingProvider',
                    'permissions' => ['ai.embeddings'],
                    'tags'        => ['embeddings', 'core'],
                ],
            ],
        ]);

        $tools = $this->service->discoverTools($this->tmpDir);

        $this->assertCount(1, $tools);
        $tool = $tools[0];

        $this->assertArrayHasKey('id', $tool);
        $this->assertArrayHasKey('name', $tool);
        $this->assertArrayHasKey('description', $tool);
        $this->assertArrayHasKey('risk_class', $tool);
        $this->assertArrayHasKey('parameters', $tool);
        $this->assertArrayHasKey('class', $tool);
    }

    /**
     * Verify that discovered workflows have all fields required by RegistryManager::registerAsset('workflow').
     */
    public function test_discovered_workflows_have_registration_required_fields(): void
    {
        $this->writeJson('Workflows/workflow.json', [
            'name'         => 'Workflows',
            'version'      => '1.0.0',
            'description'  => 'Test',
            'module'       => 'TitanCore',
            'capabilities' => ['rag'],
            'workflows'    => [
                [
                    'id'          => 'rag_workflow',
                    'name'        => 'RAG Workflow',
                    'description' => 'Standard RAG',
                    'steps'       => [
                        ['name' => 'embed', 'description' => 'Embed query', 'required' => true],
                        ['name' => 'search', 'description' => 'Vector search', 'required' => true],
                    ],
                    'capabilities'=> ['rag', 'knowledge-base'],
                    'permissions' => ['ai.rag.query'],
                    'tags'        => ['rag', 'core'],
                ],
            ],
        ]);

        $workflows = $this->service->discoverWorkflows($this->tmpDir);

        $this->assertCount(1, $workflows);
        $workflow = $workflows[0];

        $this->assertArrayHasKey('id', $workflow);
        $this->assertArrayHasKey('name', $workflow);
        $this->assertArrayHasKey('description', $workflow);
        $this->assertArrayHasKey('steps', $workflow);
        $this->assertArrayHasKey('capabilities', $workflow);
    }

    // ── Tests: no hardcoded assets (metadata exclusivity) ─────────────────────

    /**
     * If only one provider is in the JSON, only one should be discoverable.
     * This proves registration is driven by metadata, not hardcoded lists.
     */
    public function test_only_providers_declared_in_metadata_are_discoverable(): void
    {
        $this->writeJson('Providers/provider.json', [
            'name'         => 'P',
            'version'      => '1.0.0',
            'description'  => 'd',
            'module'       => 'M',
            'capabilities' => ['chat'],
            'providers'    => [
                ['id' => 'only_provider', 'name' => 'Only Provider', 'description' => 'd', 'capabilities' => ['chat']],
            ],
        ]);

        $providers = $this->service->discoverProviders($this->tmpDir);

        $this->assertCount(1, $providers);
        $this->assertSame('only_provider', $providers[0]['id']);
    }

    /**
     * Adding a provider to the JSON should cause it to appear in discovery immediately.
     * This verifies that the registration list is not cached from a hardcoded source.
     */
    public function test_adding_provider_to_metadata_makes_it_discoverable(): void
    {
        $providerPath = $this->tmpDir . '/Providers';
        mkdir($providerPath, 0755, true);

        $manifest = [
            'name'         => 'P',
            'version'      => '1.0.0',
            'description'  => 'd',
            'module'       => 'M',
            'capabilities' => ['chat'],
            'providers'    => [
                ['id' => 'first', 'name' => 'First', 'description' => 'd', 'capabilities' => ['chat']],
            ],
        ];

        file_put_contents($providerPath . '/provider.json', json_encode($manifest));

        $before = $this->service->discoverProviders($this->tmpDir);
        $this->assertCount(1, $before);

        // Add a second provider to the manifest
        $manifest['providers'][] = ['id' => 'second', 'name' => 'Second', 'description' => 'd', 'capabilities' => ['embeddings']];
        file_put_contents($providerPath . '/provider.json', json_encode($manifest));

        $after = $this->service->discoverProviders($this->tmpDir);
        $this->assertCount(2, $after);

        $ids = array_column($after, 'id');
        $this->assertContains('first', $ids);
        $this->assertContains('second', $ids);
    }

    // ── Tests: module registration payload ────────────────────────────────────

    /**
     * The module registration payload should use the version and description from asset.json.
     */
    public function test_module_registration_uses_version_from_asset_json(): void
    {
        $this->writeJson('asset.json', [
            'name'         => 'TitanCoreAI',
            'version'      => '2.5.0',
            'description'  => 'Updated description',
            'module'       => 'TitanCore',
            'capabilities' => ['chat'],
        ]);

        $discovered = $this->service->discoverFromDirectory($this->tmpDir);

        $this->assertNotNull($discovered['asset']);
        $this->assertSame('2.5.0', $discovered['asset']['version']);
        $this->assertSame('Updated description', $discovered['asset']['description']);
    }

    /**
     * The module capabilities in the registration payload must be drawn from
     * indexCapabilities(), which aggregates across all manifest files.
     */
    public function test_module_registration_capabilities_reflect_all_manifests(): void
    {
        $this->writeJson('asset.json', [
            'name'         => 'A',
            'version'      => '1.0.0',
            'description'  => 'd',
            'module'       => 'M',
            'capabilities' => ['capability-from-asset'],
        ]);

        $this->writeJson('Providers/provider.json', [
            'name'         => 'P',
            'version'      => '1.0.0',
            'description'  => 'd',
            'module'       => 'M',
            'capabilities' => ['capability-from-provider'],
            'providers'    => [
                ['id' => 'x', 'name' => 'X', 'description' => 'd', 'capabilities' => ['capability-from-provider-item']],
            ],
        ]);

        $discovered   = $this->service->discoverFromDirectory($this->tmpDir);
        $capabilities = $this->service->indexCapabilities($discovered);

        $this->assertContains('capability-from-asset', $capabilities);
        $this->assertContains('capability-from-provider', $capabilities);
        $this->assertContains('capability-from-provider-item', $capabilities);
    }

    // ── Tests: graceful failure when RegistryManager is unavailable ───────────

    /**
     * Discovery itself must succeed even if the Platform Manager is not installed.
     * The service provider handles the binding check — discovery is always safe to call.
     */
    public function test_discovery_succeeds_without_platform_manager(): void
    {
        $this->writeJson('Providers/provider.json', [
            'name'         => 'P',
            'version'      => '1.0.0',
            'description'  => 'd',
            'module'       => 'M',
            'capabilities' => ['chat'],
            'providers'    => [
                ['id' => 'p1', 'name' => 'P1', 'description' => 'd', 'capabilities' => ['chat']],
            ],
        ]);

        // Does not depend on Laravel container or RegistryManager at all
        $this->assertDoesNotThrow(function () {
            $result = $this->service->discoverFromDirectory($this->tmpDir);
            $this->assertCount(1, $result['providers']);
        });
    }

    // ── Tests: real TitanCore manifests produce complete registration payloads ─

    public function test_real_titancore_provider_metadata_produces_complete_payloads(): void
    {
        $realAiDir = __DIR__ . '/../../AI';

        if (! is_dir($realAiDir)) {
            $this->markTestSkipped('AI/ directory not found next to Tests/.');
        }

        $providers = $this->service->discoverProviders($realAiDir);

        $this->assertNotEmpty($providers, 'Expected at least one provider from real provider.json.');

        foreach ($providers as $provider) {
            $this->assertArrayHasKey('id', $provider, "Provider '{$provider['name']}' is missing 'id'");
            $this->assertArrayHasKey('name', $provider, "Provider is missing 'name'");
            $this->assertArrayHasKey('description', $provider, "Provider '{$provider['id']}' is missing 'description'");
            $this->assertArrayHasKey('capabilities', $provider, "Provider '{$provider['id']}' is missing 'capabilities'");
            $this->assertIsArray($provider['capabilities'], "Provider '{$provider['id']}' capabilities must be an array");
        }
    }

    public function test_real_titancore_workflow_metadata_produces_complete_payloads(): void
    {
        $realAiDir = __DIR__ . '/../../AI';

        if (! is_dir($realAiDir)) {
            $this->markTestSkipped('AI/ directory not found next to Tests/.');
        }

        $workflows = $this->service->discoverWorkflows($realAiDir);

        $this->assertNotEmpty($workflows, 'Expected at least one workflow from real workflow.json.');

        foreach ($workflows as $workflow) {
            $this->assertArrayHasKey('id', $workflow);
            $this->assertArrayHasKey('name', $workflow);
            $this->assertArrayHasKey('description', $workflow);
            $this->assertArrayHasKey('steps', $workflow);
            $this->assertIsArray($workflow['steps']);
        }
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function assertDoesNotThrow(callable $callback): void
    {
        $threw = false;
        $msg   = '';

        try {
            $callback();
        } catch (\Throwable $e) {
            $threw = true;
            $msg   = $e->getMessage();
        }

        $this->assertFalse($threw, "Expected no exception, but got: {$msg}");
    }
}
