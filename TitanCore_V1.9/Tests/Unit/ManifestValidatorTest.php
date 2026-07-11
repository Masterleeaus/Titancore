<?php

namespace Modules\TitanCore\Tests\Unit;

use Modules\TitanCore\AI\ManifestValidationIssue;
use Modules\TitanCore\AI\ManifestValidator;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for ManifestValidator.
 *
 * Uses a temporary directory with synthetic ai_tools.json manifests
 * so the tests remain isolated from the real module tree.
 */
class ManifestValidatorTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tmpDir = sys_get_temp_dir().'/titan_manifest_test_'.uniqid('', true);
        mkdir($this->tmpDir, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->rimraf($this->tmpDir);
        parent::tearDown();
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function makeModule(string $name, array $manifest): string
    {
        $moduleDir    = $this->tmpDir.'/'.$name;
        $manifestsDir = $moduleDir.'/manifests';

        mkdir($manifestsDir, 0755, true);
        file_put_contents($manifestsDir.'/ai_tools.json', json_encode($manifest));

        return $moduleDir;
    }

    private function rimraf(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir.'/'.$item;
            is_dir($path) ? $this->rimraf($path) : unlink($path);
        }

        rmdir($dir);
    }

    private function validTool(array $overrides = []): array
    {
        return array_merge([
            'id'          => 'test_tool',
            'description' => 'A test tool',
            'input_schema'=> ['company_id' => ['type' => 'integer', 'required' => true]],
            'risk_class'  => 'read',
        ], $overrides);
    }

    // ── Tests ─────────────────────────────────────────────────────────────────

    public function test_valid_manifest_produces_no_issues(): void
    {
        $this->makeModule('ValidModule', [
            'module' => 'ValidModule',
            'tools'  => [$this->validTool()],
        ]);

        $validator = new ManifestValidator($this->tmpDir);
        $issues    = $validator->validateAll();

        $this->assertEmpty($issues, 'Expected no issues for a valid manifest.');
    }

    public function test_manifest_version_alias_is_supported(): void
    {
        $this->makeModule('VersionedModule', [
            'module' => 'VersionedModule',
            'manifest_version' => '1.0.0',
            'tools' => [$this->validTool()],
        ]);

        $validator = new ManifestValidator($this->tmpDir);
        $issues = $validator->validateAll();

        $this->assertEmpty($issues, 'Expected manifest_version 1.0.0 to be accepted.');
    }

    public function test_unsupported_manifest_version_produces_error(): void
    {
        $this->makeModule('UnsupportedVersion', [
            'module' => 'UnsupportedVersion',
            'manifest_version' => '9.9.9',
            'tools' => [$this->validTool()],
        ]);

        $validator = new ManifestValidator($this->tmpDir);
        $issues = $validator->validateAll();
        $errors = array_filter($issues, fn (ManifestValidationIssue $i) => $i->isError());

        $this->assertNotEmpty($errors);
        $messages = array_map(fn (ManifestValidationIssue $i) => $i->message(), array_values($errors));
        $this->assertStringContainsString('Unsupported manifest_version', implode(' ', $messages));
    }

    public function test_missing_description_produces_error(): void
    {
        $tool = $this->validTool();
        unset($tool['description']);

        $this->makeModule('MissingDesc', [
            'tools' => [$tool],
        ]);

        $validator = new ManifestValidator($this->tmpDir);
        $issues    = $validator->validateAll();
        $errors    = array_filter($issues, fn (ManifestValidationIssue $i) => $i->isError());

        $this->assertNotEmpty($errors);

        $messages = array_map(fn (ManifestValidationIssue $i) => $i->message(), array_values($errors));
        $this->assertStringContainsString('description', implode(' ', $messages));
    }

    public function test_missing_risk_class_produces_error(): void
    {
        $tool = $this->validTool();
        unset($tool['risk_class']);

        $this->makeModule('MissingRisk', [
            'tools' => [$tool],
        ]);

        $validator = new ManifestValidator($this->tmpDir);
        $issues    = $validator->validateAll();
        $errors    = array_filter($issues, fn (ManifestValidationIssue $i) => $i->isError());

        $messages = array_map(fn (ManifestValidationIssue $i) => $i->message(), array_values($errors));
        $this->assertStringContainsString('risk_class', implode(' ', $messages));
    }

    public function test_missing_parameters_schema_produces_error(): void
    {
        $tool = $this->validTool();
        unset($tool['input_schema']);

        $this->makeModule('MissingParams', [
            'tools' => [$tool],
        ]);

        $validator = new ManifestValidator($this->tmpDir);
        $issues    = $validator->validateAll();
        $errors    = array_filter($issues, fn (ManifestValidationIssue $i) => $i->isError());

        $messages = array_map(fn (ManifestValidationIssue $i) => $i->message(), array_values($errors));
        $this->assertStringContainsString('parameters', implode(' ', $messages));
    }

    public function test_nonexistent_class_produces_error(): void
    {
        $tool = $this->validTool(['class' => 'Modules\\DoesNotExist\\Tools\\FakeTool']);

        $this->makeModule('MissingClass', [
            'tools' => [$tool],
        ]);

        $validator = new ManifestValidator($this->tmpDir);
        $issues    = $validator->validateAll();
        $errors    = array_filter($issues, fn (ManifestValidationIssue $i) => $i->isError());

        $messages = array_map(fn (ManifestValidationIssue $i) => $i->message(), array_values($errors));
        $this->assertStringContainsString('does not exist', implode(' ', $messages));
    }

    public function test_class_with_wrong_execute_return_type_produces_error(): void
    {
        // Inline an anonymous class via a real registered class approach — we use
        // a fixture class defined below in this file.
        $tool = $this->validTool(['class' => ManifestValidatorFixtureWrongReturn::class]);

        $this->makeModule('WrongReturnType', [
            'tools' => [$tool],
        ]);

        $validator = new ManifestValidator($this->tmpDir);
        $issues    = $validator->validateAll();

        // Should have an error about non-array return type
        $errors = array_filter($issues, fn (ManifestValidationIssue $i) => $i->isError());
        $messages = array_map(fn (ManifestValidationIssue $i) => $i->message(), array_values($errors));
        $this->assertStringContainsString('array', implode(' ', $messages));
    }

    public function test_class_with_correct_execute_return_type_is_valid(): void
    {
        $tool = $this->validTool(['class' => ManifestValidatorFixtureCorrect::class]);

        $this->makeModule('CorrectClass', [
            'tools' => [$tool],
        ]);

        $validator = new ManifestValidator($this->tmpDir);
        $issues    = $validator->validateAll();
        $errors    = array_filter($issues, fn (ManifestValidationIssue $i) => $i->isError());

        $this->assertEmpty($errors, 'A properly typed execute() should produce no errors.');
    }

    public function test_module_without_ai_tools_manifest_produces_no_issues(): void
    {
        // Create a module dir with no manifests/ directory
        mkdir($this->tmpDir.'/EmptyModule', 0755, true);

        $validator = new ManifestValidator($this->tmpDir);
        $issues    = $validator->validateAll();

        $this->assertEmpty($issues);
    }

    public function test_validate_single_module_by_name(): void
    {
        // Create two modules; only the first has an issue
        $badTool = $this->validTool();
        unset($badTool['description']);

        $this->makeModule('BadModule', ['tools' => [$badTool]]);
        $this->makeModule('GoodModule', ['tools' => [$this->validTool()]]);

        $validator = new ManifestValidator($this->tmpDir);

        $allIssues  = $validator->validateAll();
        $badIssues  = $validator->validateModule('BadModule');
        $goodIssues = $validator->validateModule('GoodModule');

        $this->assertNotEmpty($badIssues, 'BadModule should have issues');
        $this->assertEmpty($goodIssues, 'GoodModule should be clean');
        $this->assertLessThanOrEqual(count($allIssues), count($badIssues));
    }

    public function test_invalid_json_produces_error(): void
    {
        $moduleDir    = $this->tmpDir.'/BadJson';
        $manifestsDir = $moduleDir.'/manifests';
        mkdir($manifestsDir, 0755, true);
        file_put_contents($manifestsDir.'/ai_tools.json', '{ invalid json ');

        $validator = new ManifestValidator($this->tmpDir);
        $issues    = $validator->validateAll();
        $errors    = array_filter($issues, fn (ManifestValidationIssue $i) => $i->isError());

        $this->assertNotEmpty($errors);
    }

    public function test_boot_check_returns_true_on_errors(): void
    {
        $badTool = $this->validTool();
        unset($badTool['description']);

        $this->makeModule('BootBad', ['tools' => [$badTool]]);

        $validator = new ManifestValidator($this->tmpDir);
        $result    = $validator->bootCheck();

        $this->assertTrue($result, 'bootCheck() should return true when errors exist.');
    }

    public function test_boot_check_returns_false_on_clean_manifests(): void
    {
        $this->makeModule('BootGood', ['tools' => [$this->validTool()]]);

        $validator = new ManifestValidator($this->tmpDir);
        $result    = $validator->bootCheck();

        $this->assertFalse($result, 'bootCheck() should return false when no errors exist.');
    }
}

// ── Fixture classes ───────────────────────────────────────────────────────────

/** Tool with wrong return type on execute() — should produce an error */
class ManifestValidatorFixtureWrongReturn
{
    public function execute(array $params): string
    {
        return 'wrong';
    }
}

/** Tool with correct array return type on execute() — should be valid */
class ManifestValidatorFixtureCorrect
{
    public function execute(array $params): array
    {
        return [];
    }
}
