<?php

namespace Modules\TitanCore\Tests\Feature;

use Modules\TitanCore\Services\AssetDiscoveryService;
use Modules\TitanCore\Support\AssetManifestValidator;
use PHPUnit\Framework\TestCase;

/**
 * Feature tests for capability indexing via AssetDiscoveryService.
 *
 * These tests use the real TitanCore metadata JSON files to verify that all
 * expected capabilities are indexed by the Platform Manager integration.
 *
 * Tests cover:
 *  - All capability strings declared in asset.json are indexed.
 *  - Provider capabilities (chat, embeddings, etc.) are merged into the index.
 *  - Workflow capabilities (rag, tool-execution, etc.) are merged.
 *  - Tool capabilities are merged.
 *  - The index contains no duplicates.
 *  - Specific known capabilities are present for the real TitanCore module.
 *  - Synthetic capability merging works regardless of the real manifest files.
 */
class CapabilityIndexingTest extends TestCase
{
    private string $tmpDir;
    private AssetDiscoveryService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tmpDir  = sys_get_temp_dir() . '/titan_capability_test_' . uniqid('', true);
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

    // ── Tests: synthetic capability indexing ──────────────────────────────────

    public function test_asset_capabilities_are_indexed(): void
    {
        $this->writeJson('asset.json', [
            'name'               => 'TestAI',
            'version'            => '1.0.0',
            'description'        => 'Test AI',
            'module'             => 'Test',
            'capabilities'       => ['chat', 'embeddings', 'rag'],
            'discovery_metadata' => [],
        ]);

        $discovered   = $this->service->discoverFromDirectory($this->tmpDir);
        $capabilities = $this->service->indexCapabilities($discovered);

        $this->assertContains('chat', $capabilities);
        $this->assertContains('embeddings', $capabilities);
        $this->assertContains('rag', $capabilities);
    }

    public function test_provider_capabilities_are_merged_into_index(): void
    {
        $this->writeJson('Providers/provider.json', [
            'name'               => 'Providers',
            'version'            => '1.0.0',
            'description'        => 'Test providers',
            'module'             => 'Test',
            'capabilities'       => ['streaming', 'moderation'],
            'providers'          => [
                [
                    'id'           => 'p1',
                    'name'         => 'P1',
                    'description'  => 'd',
                    'capabilities' => ['streaming', 'moderation', 'image-generation'],
                ],
            ],
        ]);

        $discovered   = $this->service->discoverFromDirectory($this->tmpDir);
        $capabilities = $this->service->indexCapabilities($discovered);

        $this->assertContains('streaming', $capabilities);
        $this->assertContains('moderation', $capabilities);
        $this->assertContains('image-generation', $capabilities);
    }

    public function test_workflow_capabilities_are_merged_into_index(): void
    {
        $this->writeJson('Workflows/workflow.json', [
            'name'               => 'Workflows',
            'version'            => '1.0.0',
            'description'        => 'Test workflows',
            'module'             => 'Test',
            'capabilities'       => ['rag', 'knowledge-base'],
            'workflows'          => [
                [
                    'id'          => 'w1',
                    'name'        => 'W1',
                    'description' => 'd',
                    'steps'       => [],
                    'capabilities'=> ['rag', 'knowledge-base'],
                ],
            ],
        ]);

        $discovered   = $this->service->discoverFromDirectory($this->tmpDir);
        $capabilities = $this->service->indexCapabilities($discovered);

        $this->assertContains('rag', $capabilities);
        $this->assertContains('knowledge-base', $capabilities);
    }

    public function test_tool_capabilities_are_merged_into_index(): void
    {
        $this->writeJson('Tools/tool.json', [
            'name'               => 'Tools',
            'version'            => '1.0.0',
            'description'        => 'Test tools',
            'module'             => 'Test',
            'capabilities'       => ['tool-execution'],
            'tools'              => [
                [
                    'id'          => 't1',
                    'name'        => 'T1',
                    'description' => 'd',
                    'risk_class'  => 'read',
                    'parameters'  => [],
                    'capabilities'=> ['tool-execution', 'function-calling'],
                ],
            ],
        ]);

        $discovered   = $this->service->discoverFromDirectory($this->tmpDir);
        $capabilities = $this->service->indexCapabilities($discovered);

        $this->assertContains('tool-execution', $capabilities);
        $this->assertContains('function-calling', $capabilities);
    }

    public function test_capabilities_have_no_duplicates(): void
    {
        // Both asset.json and provider.json declare 'chat' — must appear once
        $this->writeJson('asset.json', [
            'name'         => 'A',
            'version'      => '1.0.0',
            'description'  => 'd',
            'module'       => 'M',
            'capabilities' => ['chat', 'embeddings'],
        ]);

        $this->writeJson('Providers/provider.json', [
            'name'         => 'P',
            'version'      => '1.0.0',
            'description'  => 'd',
            'module'       => 'M',
            'capabilities' => ['chat', 'streaming'],
            'providers'    => [
                ['id' => 'x', 'name' => 'X', 'description' => 'd', 'capabilities' => ['chat']],
            ],
        ]);

        $discovered   = $this->service->discoverFromDirectory($this->tmpDir);
        $capabilities = $this->service->indexCapabilities($discovered);

        $this->assertSame(
            array_unique($capabilities),
            $capabilities,
            'Expected no duplicate capability strings in the index.'
        );

        // chat appears in asset + provider capabilities + provider item
        $chatCount = count(array_filter($capabilities, fn ($c) => $c === 'chat'));
        $this->assertSame(1, $chatCount, "Expected exactly one 'chat' entry, got {$chatCount}.");
    }

    public function test_all_manifest_types_contribute_to_index(): void
    {
        $this->writeJson('asset.json', [
            'name'         => 'A',
            'version'      => '1.0.0',
            'description'  => 'd',
            'module'       => 'M',
            'capabilities' => ['from-asset'],
        ]);

        $this->writeJson('Providers/provider.json', [
            'name'         => 'P',
            'version'      => '1.0.0',
            'description'  => 'd',
            'module'       => 'M',
            'capabilities' => ['from-provider'],
            'providers'    => [['id' => 'p', 'name' => 'P', 'description' => 'd', 'capabilities' => ['from-provider-item']]],
        ]);

        $this->writeJson('Workflows/workflow.json', [
            'name'         => 'W',
            'version'      => '1.0.0',
            'description'  => 'd',
            'module'       => 'M',
            'capabilities' => ['from-workflow'],
            'workflows'    => [['id' => 'w', 'name' => 'W', 'description' => 'd', 'steps' => [], 'capabilities' => ['from-workflow-item']]],
        ]);

        $this->writeJson('Tools/tool.json', [
            'name'         => 'T',
            'version'      => '1.0.0',
            'description'  => 'd',
            'module'       => 'M',
            'capabilities' => ['from-tool'],
            'tools'        => [['id' => 't', 'name' => 'T', 'description' => 'd', 'risk_class' => 'read', 'parameters' => [], 'capabilities' => ['from-tool-item']]],
        ]);

        $this->writeJson('Agents/agent.json', [
            'name'         => 'AG',
            'version'      => '1.0.0',
            'description'  => 'd',
            'module'       => 'M',
            'capabilities' => ['from-agent'],
        ]);

        $discovered   = $this->service->discoverFromDirectory($this->tmpDir);
        $capabilities = $this->service->indexCapabilities($discovered);

        foreach (['from-asset', 'from-provider', 'from-provider-item', 'from-workflow', 'from-workflow-item', 'from-tool', 'from-tool-item', 'from-agent'] as $expected) {
            $this->assertContains($expected, $capabilities, "Missing capability: {$expected}");
        }
    }

    // ── Tests: real TitanCore metadata files ──────────────────────────────────

    public function test_real_titancore_ai_dir_indexes_expected_capabilities(): void
    {
        $realAiDir = __DIR__ . '/../../AI';

        if (! is_dir($realAiDir)) {
            $this->markTestSkipped('AI/ directory not found next to Tests/.');
        }

        $discovered   = $this->service->discoverFromDirectory($realAiDir);
        $capabilities = $this->service->indexCapabilities($discovered);

        $expectedCapabilities = [
            'chat',
            'embeddings',
            'rag',
            'vector-store',
            'tool-execution',
            'function-calling',
            'streaming',
            'provider-failover',
            'model-routing',
        ];

        foreach ($expectedCapabilities as $cap) {
            $this->assertContains(
                $cap,
                $capabilities,
                "Expected capability \"{$cap}\" to be indexed from the real TitanCore AI metadata."
            );
        }
    }

    public function test_real_titancore_index_has_no_duplicates(): void
    {
        $realAiDir = __DIR__ . '/../../AI';

        if (! is_dir($realAiDir)) {
            $this->markTestSkipped('AI/ directory not found next to Tests/.');
        }

        $discovered   = $this->service->discoverFromDirectory($realAiDir);
        $capabilities = $this->service->indexCapabilities($discovered);

        $this->assertSame(
            array_unique($capabilities),
            $capabilities,
            'Capability index from real TitanCore metadata contains duplicates.'
        );
    }

    public function test_real_titancore_provider_manifest_contributes_provider_capabilities(): void
    {
        $realAiDir = __DIR__ . '/../../AI';

        if (! is_dir($realAiDir)) {
            $this->markTestSkipped('AI/ directory not found next to Tests/.');
        }

        $providers = $this->service->discoverProviders($realAiDir);

        // The real provider.json must expose at least OpenAI chat and OpenAI embeddings
        $ids = array_column($providers, 'id');

        $this->assertContains('openai_chat', $ids, 'Expected openai_chat provider in real provider.json');
        $this->assertContains('openai_embedding', $ids, 'Expected openai_embedding provider in real provider.json');
        $this->assertContains('null_chat', $ids, 'Expected null_chat fallback provider in real provider.json');
    }
}
