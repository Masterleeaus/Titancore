<?php

namespace Modules\TitanCore\Tests\Unit;

use Modules\TitanCore\Services\AssetDiscoveryService;
use Modules\TitanCore\Support\AssetManifestValidator;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for AssetDiscoveryService.
 *
 * Uses a temporary directory with synthetic metadata JSON files so tests remain
 * isolated from the real module tree and filesystem layout.
 *
 * Every manifest type uses a registry format with an items array:
 *   provider.json → 'providers' array
 *   agent.json    → 'agents'    array
 *   tool.json     → 'tools'     array
 *   prompt.json   → 'prompts'   array
 *   workflow.json → 'workflows' array
 */
class AssetDiscoveryTest extends TestCase
{
    private string $tmpDir;
    private AssetDiscoveryService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tmpDir  = sys_get_temp_dir() . '/titan_discovery_test_' . uniqid('', true);
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

    // ── Fixture factories ─────────────────────────────────────────────────────

    private function minimalAsset(): array
    {
        return [
            'name'               => 'TestAI',
            'version'            => '1.0.0',
            'description'        => 'Test AI subsystem',
            'schema_version'     => '1.0.0',
            'module'             => 'TestModule',
            'capabilities'       => ['chat', 'embeddings'],
            'discovery_metadata' => ['asset_type' => 'ai_subsystem'],
        ];
    }

    private function minimalProvider(): array
    {
        return [
            'name'               => 'TestProviders',
            'version'            => '1.0.0',
            'description'        => 'Test providers',
            'schema_version'     => '1.0.0',
            'module'             => 'TestModule',
            'capabilities'       => ['chat'],
            'discovery_metadata' => ['asset_type' => 'provider_registry'],
            'providers'          => [
                [
                    'id'             => 'test_chat',
                    'name'           => 'Test Chat',
                    'description'    => 'A test chat provider',
                    'capabilities'   => ['chat'],
                    'models'         => ['test-model'],
                    'authentication' => ['type' => 'none'],
                    'rate_limits'    => [],
                    'cost_tracking'  => ['enabled' => false],
                    'failover'       => [],
                    'health_check'   => false,
                ],
            ],
        ];
    }

    private function minimalAgent(): array
    {
        return [
            'name'               => 'TestAgents',
            'version'            => '1.0.0',
            'description'        => 'Test agents',
            'schema_version'     => '1.0.0',
            'module'             => 'TestModule',
            'capabilities'       => ['chat', 'rag', 'tool-execution'],
            'discovery_metadata' => ['asset_type' => 'agent_registry'],
            'agents'             => [
                [
                    'id'             => 'test_orchestrator',
                    'name'           => 'Test Orchestrator',
                    'description'    => 'A test orchestrator agent',
                    'class'          => 'Some\\Class',
                    'capabilities'   => ['chat', 'rag', 'tool-execution'],
                    'assigned_tools' => ['test_tool'],
                    'prompt_library' => ['test_prompt'],
                    'memory'         => 'session',
                    'retrieval'      => 'vector_store',
                    'policies'       => ['audit_all_tool_calls'],
                    'permissions'    => ['ai.chat'],
                    'tags'           => ['orchestrator', 'core'],
                ],
            ],
        ];
    }

    private function minimalTool(): array
    {
        return [
            'name'               => 'TestTools',
            'version'            => '1.0.0',
            'description'        => 'Test tools',
            'schema_version'     => '1.0.0',
            'module'             => 'TestModule',
            'capabilities'       => ['tool-execution'],
            'discovery_metadata' => ['asset_type' => 'tool_registry'],
            'tools'              => [
                [
                    'id'           => 'test_tool',
                    'name'         => 'Test Tool',
                    'description'  => 'A test tool',
                    'risk_class'   => 'read',
                    'parameters'   => [['name' => 'query', 'type' => 'string', 'required' => true]],
                    'capabilities' => ['tool-execution'],
                ],
            ],
        ];
    }

    private function minimalWorkflow(): array
    {
        return [
            'name'               => 'TestWorkflows',
            'version'            => '1.0.0',
            'description'        => 'Test workflows',
            'schema_version'     => '1.0.0',
            'module'             => 'TestModule',
            'capabilities'       => ['rag'],
            'discovery_metadata' => ['asset_type' => 'workflow_registry'],
            'workflows'          => [
                [
                    'id'           => 'test_workflow',
                    'name'         => 'Test Workflow',
                    'description'  => 'A test workflow',
                    'steps'        => [['name' => 'step_one', 'description' => 'Step one', 'required' => true]],
                    'capabilities' => ['rag'],
                ],
            ],
        ];
    }

    private function minimalPrompt(): array
    {
        return [
            'name'               => 'TestPrompts',
            'version'            => '1.0.0',
            'description'        => 'Test prompts',
            'schema_version'     => '1.0.0',
            'module'             => 'TestModule',
            'discovery_metadata' => ['asset_type' => 'prompt_library'],
            'prompts'            => [
                [
                    'id'          => 'test_prompt',
                    'name'        => 'Test Prompt',
                    'description' => 'A test prompt',
                    'category'    => 'system',
                    'variables'   => [],
                ],
            ],
        ];
    }

    // ── Tests: directory handling ─────────────────────────────────────────────

    public function test_discover_from_missing_directory_returns_empty_with_error(): void
    {
        $result = $this->service->discoverFromDirectory('/non/existent/path');

        $this->assertNull($result['asset']);
        $this->assertEmpty($result['providers']);
        $this->assertEmpty($result['agents']);
        $this->assertEmpty($result['tools']);
        $this->assertEmpty($result['prompts']);
        $this->assertEmpty($result['workflows']);
        $this->assertNotEmpty($result['errors']);
        $this->assertStringContainsString('not found', $result['errors'][0]);
    }

    public function test_discover_from_empty_directory_returns_empty_results(): void
    {
        $result = $this->service->discoverFromDirectory($this->tmpDir);

        $this->assertNull($result['asset']);
        $this->assertEmpty($result['providers']);
        $this->assertEmpty($result['agents']);
        $this->assertEmpty($result['errors']);
    }

    // ── Tests: asset.json discovery ──────────────────────────────────────────

    public function test_discovers_asset_manifest(): void
    {
        $this->writeJson('asset.json', $this->minimalAsset());

        $result = $this->service->discoverFromDirectory($this->tmpDir);

        $this->assertNotNull($result['asset']);
        $this->assertSame('TestAI', $result['asset']['name']);
        $this->assertSame('1.0.0', $result['asset']['version']);
        $this->assertContains('chat', $result['asset']['capabilities']);
    }

    public function test_invalid_asset_json_produces_error_and_null_asset(): void
    {
        $path = $this->tmpDir . '/asset.json';
        file_put_contents($path, '{invalid json}');

        $result = $this->service->discoverFromDirectory($this->tmpDir);

        $this->assertNull($result['asset']);
        $this->assertNotEmpty($result['errors']);
    }

    public function test_asset_manifest_missing_required_field_produces_error(): void
    {
        $data = $this->minimalAsset();
        unset($data['capabilities']);
        $this->writeJson('asset.json', $data);

        $result = $this->service->discoverFromDirectory($this->tmpDir);

        $this->assertNull($result['asset']);
        $this->assertNotEmpty($result['errors']);
    }

    // ── Tests: provider discovery ─────────────────────────────────────────────

    public function test_discovers_providers_from_provider_json(): void
    {
        $this->writeJson('Providers/provider.json', $this->minimalProvider());

        $result = $this->service->discoverFromDirectory($this->tmpDir);

        $this->assertCount(1, $result['providers']);
        $this->assertSame('test_chat', $result['providers'][0]['id']);
        $this->assertSame('Test Chat', $result['providers'][0]['name']);
    }

    public function test_invalid_provider_json_returns_empty_providers(): void
    {
        mkdir($this->tmpDir . '/Providers', 0755, true);
        file_put_contents($this->tmpDir . '/Providers/provider.json', 'not-json');

        $result = $this->service->discoverFromDirectory($this->tmpDir);

        $this->assertEmpty($result['providers']);
        $this->assertNotEmpty($result['errors']);
    }

    public function test_discover_providers_convenience_method(): void
    {
        $this->writeJson('Providers/provider.json', $this->minimalProvider());

        $providers = $this->service->discoverProviders($this->tmpDir);

        $this->assertCount(1, $providers);
        $this->assertSame('test_chat', $providers[0]['id']);
    }

    public function test_discover_providers_missing_file_returns_empty(): void
    {
        $providers = $this->service->discoverProviders($this->tmpDir);

        $this->assertEmpty($providers);
    }

    // ── Tests: agent discovery ────────────────────────────────────────────────

    public function test_discovers_agents_from_agent_json(): void
    {
        $this->writeJson('Agents/agent.json', $this->minimalAgent());

        $result = $this->service->discoverFromDirectory($this->tmpDir);

        $this->assertCount(1, $result['agents']);
        $this->assertSame('test_orchestrator', $result['agents'][0]['id']);
        $this->assertSame('Test Orchestrator', $result['agents'][0]['name']);
    }

    public function test_agent_fields_are_extracted_correctly(): void
    {
        $this->writeJson('Agents/agent.json', $this->minimalAgent());

        $result = $this->service->discoverFromDirectory($this->tmpDir);
        $agent  = $result['agents'][0];

        $this->assertArrayHasKey('id', $agent);
        $this->assertArrayHasKey('name', $agent);
        $this->assertArrayHasKey('description', $agent);
        $this->assertArrayHasKey('capabilities', $agent);
        $this->assertArrayHasKey('assigned_tools', $agent);
        $this->assertArrayHasKey('prompt_library', $agent);
        $this->assertArrayHasKey('memory', $agent);
        $this->assertArrayHasKey('retrieval', $agent);
        $this->assertSame('session', $agent['memory']);
        $this->assertSame('vector_store', $agent['retrieval']);
    }

    public function test_discover_agents_convenience_method(): void
    {
        $this->writeJson('Agents/agent.json', $this->minimalAgent());

        $agents = $this->service->discoverAgents($this->tmpDir);

        $this->assertCount(1, $agents);
        $this->assertSame('test_orchestrator', $agents[0]['id']);
    }

    public function test_invalid_agent_json_returns_empty_and_does_not_block_other_discovery(): void
    {
        mkdir($this->tmpDir . '/Agents', 0755, true);
        file_put_contents($this->tmpDir . '/Agents/agent.json', '{broken}');
        $this->writeJson('Tools/tool.json', $this->minimalTool());

        $result = $this->service->discoverFromDirectory($this->tmpDir);

        $this->assertEmpty($result['agents']);
        $this->assertCount(1, $result['tools']);
        $this->assertNotEmpty($result['errors']);
    }

    public function test_agent_json_missing_agents_array_produces_error(): void
    {
        // agent.json without the 'agents' key should fail validation and be skipped
        $this->writeJson('Agents/agent.json', [
            'name'         => 'SingleAgent',
            'version'      => '1.0.0',
            'description'  => 'Old-style single object (invalid)',
            'module'       => 'TestModule',
            'capabilities' => ['chat'],
            // intentionally missing 'agents' array
        ]);

        $result = $this->service->discoverFromDirectory($this->tmpDir);

        $this->assertEmpty($result['agents'], 'agent.json without agents array must be rejected.');
        $this->assertNotEmpty($result['errors']);
    }

    // ── Tests: tool discovery ─────────────────────────────────────────────────

    public function test_discovers_tools_from_tool_json(): void
    {
        $this->writeJson('Tools/tool.json', $this->minimalTool());

        $result = $this->service->discoverFromDirectory($this->tmpDir);

        $this->assertCount(1, $result['tools']);
        $this->assertSame('test_tool', $result['tools'][0]['id']);
    }

    public function test_discover_tools_convenience_method(): void
    {
        $this->writeJson('Tools/tool.json', $this->minimalTool());

        $tools = $this->service->discoverTools($this->tmpDir);

        $this->assertCount(1, $tools);
        $this->assertSame('Test Tool', $tools[0]['name']);
    }

    // ── Tests: workflow discovery ─────────────────────────────────────────────

    public function test_discovers_workflows_from_workflow_json(): void
    {
        $this->writeJson('Workflows/workflow.json', $this->minimalWorkflow());

        $result = $this->service->discoverFromDirectory($this->tmpDir);

        $this->assertCount(1, $result['workflows']);
        $this->assertSame('test_workflow', $result['workflows'][0]['id']);
    }

    public function test_discover_workflows_convenience_method(): void
    {
        $this->writeJson('Workflows/workflow.json', $this->minimalWorkflow());

        $workflows = $this->service->discoverWorkflows($this->tmpDir);

        $this->assertCount(1, $workflows);
    }

    // ── Tests: prompt discovery ───────────────────────────────────────────────

    public function test_discovers_prompts_from_prompt_json(): void
    {
        $this->writeJson('Prompts/prompt.json', $this->minimalPrompt());

        $result = $this->service->discoverFromDirectory($this->tmpDir);

        $this->assertCount(1, $result['prompts']);
        $this->assertSame('test_prompt', $result['prompts'][0]['id']);
    }

    // ── Tests: full discovery ─────────────────────────────────────────────────

    public function test_discovers_all_asset_types_from_complete_directory(): void
    {
        $this->writeJson('asset.json', $this->minimalAsset());
        $this->writeJson('Providers/provider.json', $this->minimalProvider());
        $this->writeJson('Agents/agent.json', $this->minimalAgent());
        $this->writeJson('Tools/tool.json', $this->minimalTool());
        $this->writeJson('Workflows/workflow.json', $this->minimalWorkflow());
        $this->writeJson('Prompts/prompt.json', $this->minimalPrompt());

        $result = $this->service->discoverFromDirectory($this->tmpDir);

        $this->assertNotNull($result['asset']);
        $this->assertCount(1, $result['providers']);
        $this->assertCount(1, $result['agents']);
        $this->assertCount(1, $result['tools']);
        $this->assertCount(1, $result['workflows']);
        $this->assertCount(1, $result['prompts']);
        $this->assertEmpty($result['errors']);
    }

    public function test_discover_all_via_module_dir(): void
    {
        // discoverAll() appends /AI to the module dir
        $aiDir = $this->tmpDir . '/AI';
        mkdir($aiDir, 0755, true);
        file_put_contents($aiDir . '/asset.json', json_encode($this->minimalAsset()));

        $result = $this->service->discoverAll($this->tmpDir);

        $this->assertNotNull($result['asset']);
        $this->assertSame('TestAI', $result['asset']['name']);
    }

    // ── Tests: one invalid manifest does not block the others ─────────────────

    public function test_invalid_provider_manifest_does_not_block_tool_and_agent_discovery(): void
    {
        mkdir($this->tmpDir . '/Providers', 0755, true);
        file_put_contents($this->tmpDir . '/Providers/provider.json', '{broken}');
        $this->writeJson('Tools/tool.json', $this->minimalTool());
        $this->writeJson('Agents/agent.json', $this->minimalAgent());

        $result = $this->service->discoverFromDirectory($this->tmpDir);

        $this->assertEmpty($result['providers']);
        $this->assertCount(1, $result['tools']);
        $this->assertCount(1, $result['agents']);
        $this->assertNotEmpty($result['errors']);
    }

    // ── Tests: capability indexing ────────────────────────────────────────────

    public function test_index_capabilities_includes_asset_root_capabilities(): void
    {
        $this->writeJson('asset.json', $this->minimalAsset());

        $discovered   = $this->service->discoverFromDirectory($this->tmpDir);
        $capabilities = $this->service->indexCapabilities($discovered);

        $this->assertContains('chat', $capabilities);
        $this->assertContains('embeddings', $capabilities);
    }

    public function test_index_capabilities_includes_registry_level_capabilities(): void
    {
        // provider.json declares 'streaming' at the registry root level
        $manifest               = $this->minimalProvider();
        $manifest['capabilities'] = ['streaming', 'moderation'];
        $this->writeJson('Providers/provider.json', $manifest);

        $discovered   = $this->service->discoverFromDirectory($this->tmpDir);
        $capabilities = $this->service->indexCapabilities($discovered);

        $this->assertContains('streaming', $capabilities);
        $this->assertContains('moderation', $capabilities);
    }

    public function test_index_capabilities_includes_per_item_capabilities(): void
    {
        $manifest                          = $this->minimalProvider();
        $manifest['providers'][0]['capabilities'] = ['chat', 'function-calling'];
        $this->writeJson('Providers/provider.json', $manifest);

        $discovered   = $this->service->discoverFromDirectory($this->tmpDir);
        $capabilities = $this->service->indexCapabilities($discovered);

        $this->assertContains('function-calling', $capabilities);
    }

    public function test_index_capabilities_includes_agent_capabilities(): void
    {
        $this->writeJson('Agents/agent.json', $this->minimalAgent());

        $discovered   = $this->service->discoverFromDirectory($this->tmpDir);
        $capabilities = $this->service->indexCapabilities($discovered);

        // Agent registry root declares ['chat', 'rag', 'tool-execution']
        $this->assertContains('rag', $capabilities);
        $this->assertContains('tool-execution', $capabilities);
    }

    public function test_index_capabilities_aggregates_from_all_manifests(): void
    {
        $this->writeJson('asset.json', $this->minimalAsset());
        $this->writeJson('Providers/provider.json', $this->minimalProvider());
        $this->writeJson('Agents/agent.json', $this->minimalAgent());
        $this->writeJson('Workflows/workflow.json', $this->minimalWorkflow());
        $this->writeJson('Tools/tool.json', $this->minimalTool());

        $discovered   = $this->service->discoverFromDirectory($this->tmpDir);
        $capabilities = $this->service->indexCapabilities($discovered);

        $this->assertContains('chat', $capabilities);       // asset.json
        $this->assertContains('embeddings', $capabilities); // asset.json
        $this->assertContains('rag', $capabilities);        // agent + workflow
        $this->assertContains('tool-execution', $capabilities); // agent + tool
        // No duplicates
        $this->assertSame(array_unique($capabilities), $capabilities);
    }

    public function test_index_capabilities_returns_empty_for_empty_discovery(): void
    {
        $discovered   = $this->service->discoverFromDirectory($this->tmpDir);
        $capabilities = $this->service->indexCapabilities($discovered);

        $this->assertEmpty($capabilities);
    }

    public function test_index_capabilities_has_no_duplicates(): void
    {
        // All manifests declare 'chat' — should appear only once
        $asset               = $this->minimalAsset();
        $asset['capabilities'] = ['chat', 'embeddings'];
        $this->writeJson('asset.json', $asset);

        $provider               = $this->minimalProvider();
        $provider['capabilities'] = ['chat', 'streaming'];
        $this->writeJson('Providers/provider.json', $provider);

        $discovered   = $this->service->discoverFromDirectory($this->tmpDir);
        $capabilities = $this->service->indexCapabilities($discovered);

        $chatCount = count(array_filter($capabilities, fn ($c) => $c === 'chat'));
        $this->assertSame(1, $chatCount, "Expected exactly one 'chat' entry, got {$chatCount}.");
    }

    // ── Tests: real TitanCore metadata ────────────────────────────────────────

    public function test_real_titancore_agent_json_produces_agents(): void
    {
        $realAiDir = __DIR__ . '/../../AI';

        if (! is_dir($realAiDir)) {
            $this->markTestSkipped('AI/ directory not found next to Tests/.');
        }

        $agents = $this->service->discoverAgents($realAiDir);

        $this->assertNotEmpty($agents, 'Expected at least one agent from real AI/Agents/agent.json.');
        $this->assertSame('titan_core_orchestrator', $agents[0]['id']);
    }

    public function test_real_titancore_all_manifests_discoverable(): void
    {
        $realAiDir = __DIR__ . '/../../AI';

        if (! is_dir($realAiDir)) {
            $this->markTestSkipped('AI/ directory not found next to Tests/.');
        }

        $result = $this->service->discoverFromDirectory($realAiDir);

        $this->assertNotNull($result['asset'], 'asset.json not discovered');
        $this->assertNotEmpty($result['providers'], 'providers not discovered');
        $this->assertNotEmpty($result['agents'], 'agents not discovered');
        $this->assertNotEmpty($result['tools'], 'tools not discovered');
        $this->assertNotEmpty($result['prompts'], 'prompts not discovered');
        $this->assertNotEmpty($result['workflows'], 'workflows not discovered');
        $this->assertEmpty($result['errors'], 'Unexpected discovery errors: ' . implode('; ', $result['errors']));
    }
}
