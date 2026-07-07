<?php

namespace Modules\TitanCore\Tests\Unit;

use Modules\TitanCore\Support\AssetManifestValidator;
use Modules\TitanCore\Support\ManifestValidationResult;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for AssetManifestValidator.
 *
 * Covers required-field presence, empty-value rejection, schema version
 * enforcement, item-level subfield validation for each manifest type, and
 * graceful-failure behaviour (no exceptions, only result objects).
 */
class AssetManifestValidationTest extends TestCase
{
    private string $tmpDir;
    private AssetManifestValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tmpDir    = sys_get_temp_dir() . '/titan_amv_test_' . uniqid('', true);
        mkdir($this->tmpDir, 0755, true);

        $this->validator = new AssetManifestValidator();
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

    private function writeManifest(string $filename, array $data): string
    {
        $path = $this->tmpDir . '/' . $filename;
        file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT));

        return $path;
    }

    private function validAsset(): array
    {
        return [
            'name'               => 'TitanCoreAI',
            'version'            => '1.9.0',
            'description'        => 'Core AI subsystem',
            'schema_version'     => '1.0.0',
            'module'             => 'TitanCore',
            'capabilities'       => ['chat', 'embeddings'],
            'discovery_metadata' => ['asset_type' => 'ai_subsystem'],
        ];
    }

    private function validProviderManifest(): array
    {
        return [
            'name'               => 'TitanCoreProviders',
            'version'            => '1.9.0',
            'description'        => 'Providers',
            'schema_version'     => '1.0.0',
            'module'             => 'TitanCore',
            'capabilities'       => ['chat'],
            'discovery_metadata' => ['asset_type' => 'provider_registry'],
            'providers'          => [
                [
                    'id'           => 'openai_chat',
                    'name'         => 'OpenAI Chat',
                    'description'  => 'OpenAI provider',
                    'capabilities' => ['chat'],
                    'models'       => ['gpt-4'],
                    'authentication' => ['type' => 'bearer_token'],
                    'rate_limits'  => [],
                    'cost_tracking'=> ['enabled' => true],
                    'failover'     => [],
                    'health_check' => true,
                ],
            ],
        ];
    }

    private function validToolManifest(): array
    {
        return [
            'name'               => 'TitanCoreTools',
            'version'            => '1.9.0',
            'description'        => 'Tools',
            'schema_version'     => '1.0.0',
            'module'             => 'TitanCore',
            'capabilities'       => ['tool-execution'],
            'discovery_metadata' => ['asset_type' => 'tool_registry'],
            'tools'              => [
                [
                    'id'          => 'embed_tool',
                    'name'        => 'Embed Tool',
                    'description' => 'Embeds text',
                    'risk_class'  => 'read',
                    'parameters'  => [['name' => 'text', 'type' => 'string', 'required' => true]],
                ],
            ],
        ];
    }

    private function validWorkflowManifest(): array
    {
        return [
            'name'               => 'TitanCoreWorkflows',
            'version'            => '1.9.0',
            'description'        => 'Workflows',
            'schema_version'     => '1.0.0',
            'module'             => 'TitanCore',
            'capabilities'       => ['rag'],
            'discovery_metadata' => ['asset_type' => 'workflow_registry'],
            'workflows'          => [
                [
                    'id'          => 'rag_workflow',
                    'name'        => 'RAG Workflow',
                    'description' => 'Standard RAG workflow',
                    'steps'       => [['name' => 'embed', 'description' => 'Embed query', 'required' => true]],
                ],
            ],
        ];
    }

    private function validPromptManifest(): array
    {
        return [
            'name'               => 'TitanCorePrompts',
            'version'            => '1.9.0',
            'description'        => 'Prompts',
            'schema_version'     => '1.0.0',
            'module'             => 'TitanCore',
            'discovery_metadata' => ['asset_type' => 'prompt_library'],
            'prompts'            => [
                [
                    'id'          => 'system_default',
                    'name'        => 'System Default',
                    'description' => 'Default system prompt',
                    'category'    => 'system',
                    'variables'   => [],
                ],
            ],
        ];
    }

    // ── Tests: file-level validation ──────────────────────────────────────────

    public function test_validate_file_returns_failure_for_missing_file(): void
    {
        $result = $this->validator->validateFile('/no/such/file.json', 'asset');

        $this->assertFalse($result->isValid());
        $this->assertStringContainsString('not found', $result->errors()[0]);
    }

    public function test_validate_file_returns_failure_for_invalid_json(): void
    {
        $path = $this->writeManifest('bad.json', []);
        file_put_contents($path, '{not: valid json}');

        $result = $this->validator->validateFile($path, 'asset');

        $this->assertFalse($result->isValid());
    }

    public function test_validate_file_succeeds_for_valid_asset_manifest(): void
    {
        $path   = $this->writeManifest('asset.json', $this->validAsset());
        $result = $this->validator->validateFile($path, 'asset');

        $this->assertTrue($result->isValid(), implode('; ', $result->allMessages()));
    }

    // ── Tests: schema_version enforcement ────────────────────────────────────

    public function test_unknown_schema_version_produces_failure(): void
    {
        $data                   = $this->validAsset();
        $data['schema_version'] = '99.0.0';

        $result = $this->validator->validateData($data, 'asset', 'asset.json');

        $this->assertFalse($result->isValid());
        $this->assertStringContainsString('schema_version', $result->errors()[0]);
    }

    public function test_missing_schema_version_is_accepted(): void
    {
        $data = $this->validAsset();
        unset($data['schema_version']);

        $result = $this->validator->validateData($data, 'asset', 'asset.json');

        $this->assertTrue($result->isValid(), implode('; ', $result->allMessages()));
    }

    // ── Tests: required field enforcement ────────────────────────────────────

    /** @dataProvider requiredFieldsProvider */
    public function test_missing_required_field_produces_failure(string $type, array $manifest, string $missingField): void
    {
        unset($manifest[$missingField]);
        $result = $this->validator->validateData($manifest, $type, "test_{$type}.json");

        $this->assertFalse($result->isValid(), "Expected failure when \"{$missingField}\" is missing from {$type} manifest.");
        $this->assertStringContainsString($missingField, implode(' ', $result->errors()));
    }

    public static function requiredFieldsProvider(): array
    {
        $asset    = self::staticValidAsset();
        $provider = self::staticValidProviderManifest();
        $tool     = self::staticValidToolManifest();
        $workflow = self::staticValidWorkflowManifest();
        $prompt   = self::staticValidPromptManifest();

        return [
            'asset: missing name'         => ['asset',    $asset,    'name'],
            'asset: missing version'       => ['asset',    $asset,    'version'],
            'asset: missing description'   => ['asset',    $asset,    'description'],
            'asset: missing capabilities'  => ['asset',    $asset,    'capabilities'],
            'asset: missing module'        => ['asset',    $asset,    'module'],
            'provider: missing providers'  => ['provider', $provider, 'providers'],
            'provider: missing module'     => ['provider', $provider, 'module'],
            'tool: missing tools'          => ['tool',     $tool,     'tools'],
            'tool: missing capabilities'   => ['tool',     $tool,     'capabilities'],
            'workflow: missing workflows'  => ['workflow', $workflow, 'workflows'],
            'prompt: missing prompts'      => ['prompt',   $prompt,   'prompts'],
            'prompt: missing module'       => ['prompt',   $prompt,   'module'],
        ];
    }

    private static function staticValidAsset(): array
    {
        return [
            'name'               => 'TitanCoreAI',
            'version'            => '1.9.0',
            'description'        => 'Core AI subsystem',
            'schema_version'     => '1.0.0',
            'module'             => 'TitanCore',
            'capabilities'       => ['chat'],
            'discovery_metadata' => [],
        ];
    }

    private static function staticValidProviderManifest(): array
    {
        return [
            'name'               => 'P',
            'version'            => '1.0.0',
            'description'        => 'D',
            'module'             => 'M',
            'capabilities'       => ['chat'],
            'providers'          => [
                ['id' => 'x', 'name' => 'X', 'description' => 'd', 'capabilities' => ['chat']],
            ],
        ];
    }

    private static function staticValidToolManifest(): array
    {
        return [
            'name'               => 'T',
            'version'            => '1.0.0',
            'description'        => 'D',
            'module'             => 'M',
            'capabilities'       => ['tool-execution'],
            'tools'              => [
                ['id' => 'x', 'name' => 'X', 'description' => 'd', 'risk_class' => 'read', 'parameters' => []],
            ],
        ];
    }

    private static function staticValidWorkflowManifest(): array
    {
        return [
            'name'               => 'W',
            'version'            => '1.0.0',
            'description'        => 'D',
            'module'             => 'M',
            'capabilities'       => ['rag'],
            'workflows'          => [
                ['id' => 'x', 'name' => 'X', 'description' => 'd', 'steps' => []],
            ],
        ];
    }

    private static function staticValidPromptManifest(): array
    {
        return [
            'name'               => 'P',
            'version'            => '1.0.0',
            'description'        => 'D',
            'module'             => 'M',
            'prompts'            => [
                ['id' => 'x', 'name' => 'X', 'description' => 'd', 'category' => 'system', 'variables' => []],
            ],
        ];
    }

    // ── Tests: empty value rejection ──────────────────────────────────────────

    public function test_empty_capabilities_array_is_reported_as_warning(): void
    {
        $data                 = $this->validAsset();
        $data['capabilities'] = [];

        $result = $this->validator->validateData($data, 'asset', 'asset.json');

        // Empty capabilities should produce a warning, not a hard failure
        $this->assertTrue($result->isValid());
        $this->assertTrue($result->hasWarnings());
    }

    public function test_empty_name_string_produces_failure(): void
    {
        $data         = $this->validAsset();
        $data['name'] = '';

        $result = $this->validator->validateData($data, 'asset', 'asset.json');

        $this->assertFalse($result->isValid());
    }

    // ── Tests: missing discovery_metadata produces warning ────────────────────

    public function test_missing_discovery_metadata_produces_warning(): void
    {
        $data = $this->validAsset();
        unset($data['discovery_metadata']);

        $result = $this->validator->validateData($data, 'asset', 'asset.json');

        $this->assertTrue($result->isValid());
        $this->assertTrue($result->hasWarnings());
        $this->assertStringContainsString('discovery_metadata', implode(' ', $result->warnings()));
    }

    // ── Tests: item-level validation ──────────────────────────────────────────

    public function test_provider_item_missing_id_produces_failure(): void
    {
        $data                      = $this->validProviderManifest();
        $data['providers'][0]['id'] = null;

        $result = $this->validator->validateData($data, 'provider', 'provider.json');

        $this->assertFalse($result->isValid());
        $this->assertStringContainsString('id', implode(' ', $result->errors()));
    }

    public function test_tool_item_missing_risk_class_produces_failure(): void
    {
        $data = $this->validToolManifest();
        unset($data['tools'][0]['risk_class']);

        $result = $this->validator->validateData($data, 'tool', 'tool.json');

        $this->assertFalse($result->isValid());
        $this->assertStringContainsString('risk_class', implode(' ', $result->errors()));
    }

    public function test_workflow_item_missing_steps_produces_failure(): void
    {
        $data = $this->validWorkflowManifest();
        unset($data['workflows'][0]['steps']);

        $result = $this->validator->validateData($data, 'workflow', 'workflow.json');

        $this->assertFalse($result->isValid());
        $this->assertStringContainsString('steps', implode(' ', $result->errors()));
    }

    public function test_prompt_item_missing_category_produces_failure(): void
    {
        $data = $this->validPromptManifest();
        unset($data['prompts'][0]['category']);

        $result = $this->validator->validateData($data, 'prompt', 'prompt.json');

        $this->assertFalse($result->isValid());
        $this->assertStringContainsString('category', implode(' ', $result->errors()));
    }

    public function test_empty_providers_array_produces_failure(): void
    {
        $data               = $this->validProviderManifest();
        $data['providers']  = [];

        $result = $this->validator->validateData($data, 'provider', 'provider.json');

        $this->assertFalse($result->isValid());
    }

    // ── Tests: validateRequiredFields ─────────────────────────────────────────

    public function test_validate_required_fields_passes_when_all_present(): void
    {
        $data   = ['name' => 'Test', 'version' => '1.0.0'];
        $result = $this->validator->validateRequiredFields($data, ['name', 'version'], 'test.json');

        $this->assertTrue($result->isValid());
    }

    public function test_validate_required_fields_fails_when_field_missing(): void
    {
        $data   = ['name' => 'Test'];
        $result = $this->validator->validateRequiredFields($data, ['name', 'version'], 'test.json');

        $this->assertFalse($result->isValid());
        $this->assertStringContainsString('version', $result->errors()[0]);
    }

    public function test_validate_required_fields_fails_when_field_empty(): void
    {
        $data   = ['name' => '', 'version' => '1.0.0'];
        $result = $this->validator->validateRequiredFields($data, ['name', 'version'], 'test.json');

        $this->assertFalse($result->isValid());
        $this->assertStringContainsString('name', $result->errors()[0]);
    }

    // ── Tests: validateAll ────────────────────────────────────────────────────

    public function test_validate_all_returns_result_per_file(): void
    {
        $assetPath    = $this->writeManifest('asset.json', $this->validAsset());
        $providerPath = $this->writeManifest('provider.json', $this->validProviderManifest());

        $results = $this->validator->validateAll([
            'asset'    => $assetPath,
            'provider' => $providerPath,
        ]);

        $this->assertArrayHasKey('asset', $results);
        $this->assertArrayHasKey('provider', $results);
        $this->assertTrue($results['asset']->isValid());
        $this->assertTrue($results['provider']->isValid());
    }

    public function test_all_valid_returns_true_when_all_pass(): void
    {
        $results = [
            ManifestValidationResult::success('a'),
            ManifestValidationResult::success('b'),
        ];

        $this->assertTrue($this->validator->allValid($results));
    }

    public function test_all_valid_returns_false_when_any_fails(): void
    {
        $results = [
            ManifestValidationResult::success('a'),
            ManifestValidationResult::failure('b', ['bad field']),
        ];

        $this->assertFalse($this->validator->allValid($results));
    }

    // ── Tests: graceful failure (no exceptions) ───────────────────────────────

    public function test_validator_never_throws_on_bad_input(): void
    {
        // Provide completely malformed data — must return a result, not throw
        $result = $this->validator->validateRequiredFields([], ['name', 'version', 'description'], 'empty.json');

        $this->assertInstanceOf(ManifestValidationResult::class, $result);
        $this->assertFalse($result->isValid());
    }

    public function test_validate_data_with_unknown_type_does_not_throw(): void
    {
        $result = $this->validator->validateData(['name' => 'X', 'version' => '1.0'], 'unknown_type', 'x.json');

        $this->assertInstanceOf(ManifestValidationResult::class, $result);
    }

    // ── Tests: real TitanCore asset.json ─────────────────────────────────────

    public function test_real_titancore_asset_json_is_valid(): void
    {
        $path = __DIR__ . '/../../AI/asset.json';

        if (! file_exists($path)) {
            $this->markTestSkipped('AI/asset.json not found.');
        }

        $result = $this->validator->validateFile($path, 'asset');

        $this->assertTrue($result->isValid(), 'AI/asset.json failed validation: ' . implode('; ', $result->allMessages()));
    }

    public function test_real_titancore_provider_json_is_valid(): void
    {
        $path = __DIR__ . '/../../AI/Providers/provider.json';

        if (! file_exists($path)) {
            $this->markTestSkipped('AI/Providers/provider.json not found.');
        }

        $result = $this->validator->validateFile($path, 'provider');

        $this->assertTrue($result->isValid(), 'provider.json failed validation: ' . implode('; ', $result->allMessages()));
    }

    public function test_real_titancore_tool_json_is_valid(): void
    {
        $path = __DIR__ . '/../../AI/Tools/tool.json';

        if (! file_exists($path)) {
            $this->markTestSkipped('AI/Tools/tool.json not found.');
        }

        $result = $this->validator->validateFile($path, 'tool');

        $this->assertTrue($result->isValid(), 'tool.json failed validation: ' . implode('; ', $result->allMessages()));
    }

    public function test_real_titancore_workflow_json_is_valid(): void
    {
        $path = __DIR__ . '/../../AI/Workflows/workflow.json';

        if (! file_exists($path)) {
            $this->markTestSkipped('AI/Workflows/workflow.json not found.');
        }

        $result = $this->validator->validateFile($path, 'workflow');

        $this->assertTrue($result->isValid(), 'workflow.json failed validation: ' . implode('; ', $result->allMessages()));
    }

    public function test_real_titancore_prompt_json_is_valid(): void
    {
        $path = __DIR__ . '/../../AI/Prompts/prompt.json';

        if (! file_exists($path)) {
            $this->markTestSkipped('AI/Prompts/prompt.json not found.');
        }

        $result = $this->validator->validateFile($path, 'prompt');

        $this->assertTrue($result->isValid(), 'prompt.json failed validation: ' . implode('; ', $result->allMessages()));
    }

    public function test_real_titancore_agent_json_is_valid(): void
    {
        $path = __DIR__ . '/../../AI/Agents/agent.json';

        if (! file_exists($path)) {
            $this->markTestSkipped('AI/Agents/agent.json not found.');
        }

        $result = $this->validator->validateFile($path, 'agent');

        $this->assertTrue($result->isValid(), 'agent.json failed validation: ' . implode('; ', $result->allMessages()));
    }
}
