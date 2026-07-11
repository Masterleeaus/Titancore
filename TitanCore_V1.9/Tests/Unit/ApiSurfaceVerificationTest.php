<?php

namespace Modules\TitanCore\Tests\Unit;

use PHPUnit\Framework\TestCase;

class ApiSurfaceVerificationTest extends TestCase
{
    public function test_api_routes_include_top_level_v1_health_endpoint(): void
    {
        $routes = file_get_contents(__DIR__ . '/../../Routes/api.php');

        $this->assertIsString($routes);
        $this->assertStringContainsString(
            "Route::get('/health', PlatformHealthController::class)->name('health');",
            $routes,
        );
    }

    public function test_platform_config_exposes_titanai_and_magicai_provider_flags(): void
    {
        $controller = file_get_contents(__DIR__ . '/../../Http/Controllers/Api/V1/PlatformController.php');

        $this->assertIsString($controller);
        $this->assertStringContainsString("'titanai_configured'", $controller);
        $this->assertStringContainsString("'magicai_configured'", $controller);
    }

    public function test_provider_failover_endpoint_uses_runtime_failover_lists(): void
    {
        $controller = file_get_contents(__DIR__ . '/../../Http/Controllers/Api/V1/ProvidersController.php');

        $this->assertIsString($controller);
        $this->assertStringContainsString("config('titan_model_runtime.failover.chat_providers', [])", $controller);
        $this->assertStringContainsString("config('titan_model_runtime.failover.embedding_providers', [])", $controller);
    }

    public function test_manifest_generation_skips_manifest_file_itself(): void
    {
        $command = file_get_contents(__DIR__ . '/../../Console/Commands/GenerateManifestCommand.php');

        $this->assertIsString($command);
        $this->assertStringContainsString("private const SKIP_FILES = ['MANIFEST.sha256'];", $command);
        $this->assertStringContainsString("if (in_array(\$relPath, self::SKIP_FILES, true))", $command);
    }
}
