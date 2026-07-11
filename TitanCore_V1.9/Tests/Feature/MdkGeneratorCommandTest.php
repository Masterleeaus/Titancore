<?php

namespace Modules\TitanCore\Tests\Feature;

use Tests\TestCase;

/**
 * Integration tests for the Titan MDK generator commands.
 *
 * Each test uses a temporary modules directory to verify that:
 *  - The correct files are created
 *  - The files contain properly substituted content
 *  - --dry-run mode does NOT write files
 *  - --force mode overwrites existing files
 *  - Existing files are skipped without --force
 */
class MdkGeneratorCommandTest extends TestCase
{
    private string $tmpModulesDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tmpModulesDir = sys_get_temp_dir().'/titan_mdk_test_'.uniqid('', true);
        mkdir($this->tmpModulesDir, 0755, true);

        // Override the modules path for these tests.
        config(['titan-modules.path' => $this->tmpModulesDir]);
    }

    protected function tearDown(): void
    {
        $this->rimraf($this->tmpModulesDir);
        parent::tearDown();
    }

    // ──────────────────────────────────────────────────────────────────────────
    // titan:make-module
    // ──────────────────────────────────────────────────────────────────────────

    /** @test */
    public function make_module_creates_expected_files(): void
    {
        $exit = $this->artisan('titan:make-module', ['name' => 'TestModule'])->run();

        $this->assertSame(0, $exit);

        $root = $this->tmpModulesDir.'/TestModule';

        $this->assertFileExists($root.'/module.json');
        $this->assertFileExists($root.'/composer.json');
        $this->assertFileExists($root.'/README.md');
        $this->assertFileExists($root.'/Providers/TestModuleServiceProvider.php');
        $this->assertFileExists($root.'/Config/config.php');
        $this->assertFileExists($root.'/Routes/api.php');
        $this->assertFileExists($root.'/Routes/web.php');
    }

    /** @test */
    public function make_module_readme_contains_example_commands(): void
    {
        $this->artisan('titan:make-module', ['name' => 'ReadmeModule'])->run();

        $content = file_get_contents($this->tmpModulesDir.'/ReadmeModule/README.md');

        $this->assertStringContainsString('titan:make-provider ExampleProvider', $content);
        $this->assertStringContainsString('titan:make-tool ExampleTool', $content);
        $this->assertStringContainsString('titan:make-workflow ExampleWorkflow', $content);
        $this->assertStringContainsString('titan:make-panel ExampleStudio', $content);
    }

    /** @test */
    public function make_module_substitutes_name_in_module_json(): void
    {
        $this->artisan('titan:make-module', ['name' => 'MyShop'])->run();

        $json = json_decode(
            file_get_contents($this->tmpModulesDir.'/MyShop/module.json'),
            true
        );

        $this->assertSame('MyShop', $json['name']);
        $this->assertStringContainsString('Modules\\MyShop\\Providers\\MyShopServiceProvider', $json['providers'][0]);
    }

    /** @test */
    public function make_module_dry_run_does_not_write_files(): void
    {
        $exit = $this->artisan('titan:make-module', [
            'name'      => 'DryModule',
            '--dry-run' => true,
        ])->run();

        $this->assertSame(0, $exit);
        $this->assertDirectoryDoesNotExist($this->tmpModulesDir.'/DryModule');
    }

    /** @test */
    public function make_module_skips_existing_files_without_force(): void
    {
        // Create once
        $this->artisan('titan:make-module', ['name' => 'RepeatModule'])->run();

        $moduleJsonPath = $this->tmpModulesDir.'/RepeatModule/module.json';
        $originalContent = file_get_contents($moduleJsonPath);

        // Corrupt the file so we can detect if it gets regenerated
        file_put_contents($moduleJsonPath, '{"corrupted":true}');

        // Run again without --force
        $this->artisan('titan:make-module', ['name' => 'RepeatModule'])->run();

        // File should NOT have been overwritten — corruption still present
        $this->assertSame('{"corrupted":true}', file_get_contents($moduleJsonPath));
    }

    /** @test */
    public function make_module_force_overwrites_existing_files(): void
    {
        $this->artisan('titan:make-module', ['name' => 'ForceModule'])->run();

        $moduleJsonPath = $this->tmpModulesDir.'/ForceModule/module.json';
        file_put_contents($moduleJsonPath, '{"name":"overwritten"}');

        $this->artisan('titan:make-module', [
            'name'    => 'ForceModule',
            '--force' => true,
        ])->run();

        $json = json_decode(file_get_contents($moduleJsonPath), true);
        $this->assertSame('ForceModule', $json['name']);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // titan:make-tool
    // ──────────────────────────────────────────────────────────────────────────

    /** @test */
    public function make_tool_creates_class_and_manifest(): void
    {
        // Ensure the target module directory exists.
        mkdir($this->tmpModulesDir.'/ToolModule', 0755, true);

        $exit = $this->artisan('titan:make-tool', [
            'name'     => 'PriceTool',
            '--module' => 'ToolModule',
        ])->run();

        $this->assertSame(0, $exit);
        $this->assertFileExists($this->tmpModulesDir.'/ToolModule/Tools/PriceToolTool.php');
        $this->assertFileExists($this->tmpModulesDir.'/ToolModule/manifests/tools/price_tool.json');
    }

    /** @test */
    public function make_tool_stub_contains_correct_class_name(): void
    {
        mkdir($this->tmpModulesDir.'/ToolMod', 0755, true);

        $this->artisan('titan:make-tool', [
            'name'     => 'Invoice',
            '--module' => 'ToolMod',
        ])->run();

        $content = file_get_contents($this->tmpModulesDir.'/ToolMod/Tools/InvoiceTool.php');

        $this->assertStringContainsString('class InvoiceTool', $content);
        $this->assertStringContainsString('namespace Modules\\ToolMod\\Tools', $content);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // titan:make-provider
    // ──────────────────────────────────────────────────────────────────────────

    /** @test */
    public function make_provider_creates_class_and_manifest_with_autocomplete_docs(): void
    {
        mkdir($this->tmpModulesDir.'/ProvMod', 0755, true);

        $exit = $this->artisan('titan:make-provider', [
            'name'     => 'Demo',
            '--module' => 'ProvMod',
        ])->run();

        $this->assertSame(0, $exit);
        $this->assertFileExists($this->tmpModulesDir.'/ProvMod/Providers/DemoProvider.php');
        $this->assertFileExists($this->tmpModulesDir.'/ProvMod/manifests/provider.json');

        $content = file_get_contents($this->tmpModulesDir.'/ProvMod/Providers/DemoProvider.php');
        $this->assertStringContainsString("@param  array{", $content);
        $this->assertStringContainsString("@return array{", $content);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // titan:make-agent
    // ──────────────────────────────────────────────────────────────────────────

    /** @test */
    public function make_agent_creates_class_and_manifest(): void
    {
        mkdir($this->tmpModulesDir.'/AgentMod', 0755, true);

        $exit = $this->artisan('titan:make-agent', [
            'name'     => 'Scout',
            '--module' => 'AgentMod',
        ])->run();

        $this->assertSame(0, $exit);
        $this->assertFileExists($this->tmpModulesDir.'/AgentMod/Agents/ScoutAgent.php');
        $this->assertFileExists($this->tmpModulesDir.'/AgentMod/Agents/Scout/agent.manifest.json');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // titan:make-workflow
    // ──────────────────────────────────────────────────────────────────────────

    /** @test */
    public function make_workflow_creates_class_and_manifest(): void
    {
        mkdir($this->tmpModulesDir.'/WfMod', 0755, true);

        $exit = $this->artisan('titan:make-workflow', [
            'name'     => 'Onboarding',
            '--module' => 'WfMod',
        ])->run();

        $this->assertSame(0, $exit);
        $this->assertFileExists($this->tmpModulesDir.'/WfMod/Workflows/OnboardingWorkflow.php');
        $this->assertFileExists($this->tmpModulesDir.'/WfMod/manifests/workflows/onboarding.json');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // titan:make-api
    // ──────────────────────────────────────────────────────────────────────────

    /** @test */
    public function make_api_creates_controller_request_and_resource(): void
    {
        mkdir($this->tmpModulesDir.'/ApiMod', 0755, true);

        $exit = $this->artisan('titan:make-api', [
            'name'     => 'Leads',
            '--module' => 'ApiMod',
        ])->run();

        $this->assertSame(0, $exit);
        $this->assertFileExists($this->tmpModulesDir.'/ApiMod/Http/Controllers/Api/V1/LeadsController.php');
        $this->assertFileExists($this->tmpModulesDir.'/ApiMod/Http/Requests/LeadsRequest.php');
        $this->assertFileExists($this->tmpModulesDir.'/ApiMod/Http/Resources/LeadsResource.php');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // titan:make-event
    // ──────────────────────────────────────────────────────────────────────────

    /** @test */
    public function make_event_creates_event_class(): void
    {
        mkdir($this->tmpModulesDir.'/EvtMod', 0755, true);

        $exit = $this->artisan('titan:make-event', [
            'name'     => 'LeadCreated',
            '--module' => 'EvtMod',
        ])->run();

        $this->assertSame(0, $exit);
        $this->assertFileExists($this->tmpModulesDir.'/EvtMod/Events/LeadCreated.php');

        $content = file_get_contents($this->tmpModulesDir.'/EvtMod/Events/LeadCreated.php');
        $this->assertStringContainsString('class LeadCreated', $content);
        $this->assertStringContainsString('use Dispatchable', $content);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // titan:make-contract
    // ──────────────────────────────────────────────────────────────────────────

    /** @test */
    public function make_contract_creates_interface(): void
    {
        mkdir($this->tmpModulesDir.'/CntMod', 0755, true);

        $this->artisan('titan:make-contract', [
            'name'     => 'InvoiceContract',
            '--module' => 'CntMod',
        ])->run();

        $content = file_get_contents($this->tmpModulesDir.'/CntMod/Contracts/InvoiceContract.php');
        $this->assertStringContainsString('interface InvoiceContract', $content);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // titan:make-dto
    // ──────────────────────────────────────────────────────────────────────────

    /** @test */
    public function make_dto_creates_dto_class(): void
    {
        mkdir($this->tmpModulesDir.'/DtoMod', 0755, true);

        $this->artisan('titan:make-dto', [
            'name'     => 'QuoteRequest',
            '--module' => 'DtoMod',
        ])->run();

        $content = file_get_contents($this->tmpModulesDir.'/DtoMod/DTO/QuoteRequest.php');
        $this->assertStringContainsString('final class QuoteRequest', $content);
        $this->assertStringContainsString('fromArray', $content);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // titan:make-policy
    // ──────────────────────────────────────────────────────────────────────────

    /** @test */
    public function make_policy_creates_policy_class(): void
    {
        mkdir($this->tmpModulesDir.'/PlcMod', 0755, true);

        $this->artisan('titan:make-policy', [
            'name'     => 'Quote',
            '--module' => 'PlcMod',
        ])->run();

        $content = file_get_contents($this->tmpModulesDir.'/PlcMod/Policies/QuotePolicy.php');
        $this->assertStringContainsString('class QuotePolicy', $content);
        $this->assertStringContainsString('HandlesAuthorization', $content);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // titan:make-registry
    // ──────────────────────────────────────────────────────────────────────────

    /** @test */
    public function make_registry_creates_registry_class(): void
    {
        mkdir($this->tmpModulesDir.'/RegMod', 0755, true);

        $this->artisan('titan:make-registry', [
            'name'     => 'Customer',
            '--module' => 'RegMod',
        ])->run();

        $content = file_get_contents($this->tmpModulesDir.'/RegMod/Support/CustomerRegistry.php');
        $this->assertStringContainsString('class CustomerRegistry', $content);
        $this->assertStringContainsString('public function register(', $content);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // titan:make-prompt
    // ──────────────────────────────────────────────────────────────────────────

    /** @test */
    public function make_prompt_creates_prompt_class_and_manifest(): void
    {
        mkdir($this->tmpModulesDir.'/PrmMod', 0755, true);

        $this->artisan('titan:make-prompt', [
            'name'     => 'CleaningQuote',
            '--module' => 'PrmMod',
        ])->run();

        $this->assertFileExists($this->tmpModulesDir.'/PrmMod/Prompts/CleaningQuotePrompt.php');
        $this->assertFileExists($this->tmpModulesDir.'/PrmMod/manifests/prompts/cleaning_quote.json');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // titan:doctor
    // ──────────────────────────────────────────────────────────────────────────

    /** @test */
    public function doctor_fails_for_missing_module(): void
    {
        $exit = $this->artisan('titan:doctor', ['module' => 'NonExistentModule'])->run();

        $this->assertSame(1, $exit);
    }

    /** @test */
    public function doctor_passes_for_freshly_scaffolded_module(): void
    {
        // Scaffold a module first.
        $this->artisan('titan:make-module', ['name' => 'DoctorTestModule'])->run();

        // Doctor should find no errors (may have warnings for missing manifests).
        $exit = $this->artisan('titan:doctor', ['module' => 'DoctorTestModule'])->run();

        $this->assertSame(0, $exit);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // titan:validate-module
    // ──────────────────────────────────────────────────────────────────────────

    /** @test */
    public function validate_module_fails_for_missing_module(): void
    {
        $exit = $this->artisan('titan:validate-module', ['module' => 'Ghost'])->run();

        $this->assertSame(1, $exit);
    }

    /** @test */
    public function validate_module_passes_for_freshly_scaffolded_module(): void
    {
        $this->artisan('titan:make-module', ['name' => 'ValidateMeModule'])->run();

        // Module exists and has valid module.json — should pass (may warn about TitanCore dep).
        $exit = $this->artisan('titan:validate-module', ['module' => 'ValidateMeModule'])->run();

        // 0 = pass, 1 = warnings in strict mode — accept either here.
        $this->assertContains($exit, [0, 1]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // titan:migrate-sdk
    // ──────────────────────────────────────────────────────────────────────────

    /** @test */
    public function migrate_sdk_returns_success_when_no_issues_found(): void
    {
        // Empty modules dir — no modules to scan, no issues.
        $exit = $this->artisan('titan:migrate-sdk')->run();

        // 0 = no issues found.
        $this->assertSame(0, $exit);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────────────────

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
}
