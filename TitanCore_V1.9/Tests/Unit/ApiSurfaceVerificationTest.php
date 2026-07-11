<?php

namespace Modules\TitanCore\Tests\Unit;

use Modules\TitanCore\Console\Commands\GenerateManifestCommand;
use Modules\TitanCore\Http\Controllers\Api\V1\PlatformController;
use Modules\TitanCore\Http\Controllers\Api\V1\ProvidersController;
use Modules\TitanCore\Services\TitanCoreModelGateway;
use PHPUnit\Framework\TestCase;

class ApiSurfaceVerificationTest extends TestCase
{
    public function test_api_routes_include_top_level_v1_health_endpoint(): void
    {
        $routes = file_get_contents(__DIR__ . '/../../Routes/api.php');

        $this->assertIsString($routes);
        $this->assertRegExp(
            "/Route::get\\('\\/health',\\s*PlatformHealthController::class\\)->name\\('health'\\);/",
            $routes,
        );
    }

    public function test_platform_config_exposes_titanai_and_magicai_provider_flags(): void
    {
        $originalConfig = $GLOBALS['__titan_config'] ?? [];

        $GLOBALS['__titan_config'] = [
            'app' => [
                'debug' => false,
                'timezone' => 'UTC',
                'locale' => 'en',
            ],
            'queue' => ['default' => 'sync'],
            'cache' => ['default' => 'file'],
            'session' => ['driver' => 'file'],
            'mail' => ['default' => 'smtp'],
            'titancore' => [
                'providers' => [
                    'titanai' => ['base_url' => 'https://gateway.example.test'],
                ],
                'magicai' => [
                    'base_url' => 'https://legacy.example.test',
                ],
            ],
        ];

        try {
            $response = (new PlatformController())->config();
            $data = $response->getData(true);

            $this->assertTrue($data['providers']['titanai_configured']);
            $this->assertTrue($data['providers']['magicai_configured']);
        } finally {
            $GLOBALS['__titan_config'] = $originalConfig;
        }
    }

    public function test_provider_failover_endpoint_uses_runtime_failover_lists(): void
    {
        $originalConfig = $GLOBALS['__titan_config'] ?? [];

        $GLOBALS['__titan_config'] = [
            'titan_model_runtime' => [
                'failover' => [
                    'enabled' => true,
                    'chat_providers' => ['openai', 'local'],
                    'embedding_providers' => ['openai'],
                ],
            ],
        ];

        try {
            $response = (new ProvidersController(new TitanCoreModelGateway()))->failover();
            $data = $response->getData(true);

            $this->assertTrue($data['enabled']);
            $this->assertSame(['openai', 'local'], $data['chat_providers']);
            $this->assertSame(['openai'], $data['embedding_providers']);
            $this->assertSame(['openai', 'local'], $data['chain']);
        } finally {
            $GLOBALS['__titan_config'] = $originalConfig;
        }
    }

    public function test_provider_failover_endpoint_falls_back_to_legacy_chain_config(): void
    {
        $originalConfig = $GLOBALS['__titan_config'] ?? [];

        $GLOBALS['__titan_config'] = [
            'titan_model_runtime' => [
                'failover' => [
                    'enabled' => true,
                    'chain' => ['openai', 'local'],
                ],
            ],
        ];

        try {
            $response = (new ProvidersController(new TitanCoreModelGateway()))->failover();
            $data = $response->getData(true);

            $this->assertSame(['openai', 'local'], $data['chat_providers']);
            $this->assertSame(['openai', 'local'], $data['embedding_providers']);
            $this->assertSame(['openai', 'local'], $data['chain']);
        } finally {
            $GLOBALS['__titan_config'] = $originalConfig;
        }
    }

    public function test_manifest_generation_skips_manifest_file_itself(): void
    {
        $tmpDir = sys_get_temp_dir() . '/titancore_manifest_' . uniqid();
        mkdir($tmpDir, 0755, true);
        file_put_contents($tmpDir . '/MANIFEST.sha256', 'stale');
        file_put_contents($tmpDir . '/keep.txt', 'keep');

        try {
            $command = new GenerateManifestCommand();
            $method = new \ReflectionMethod(GenerateManifestCommand::class, 'collectFiles');
            $method->setAccessible(true);

            $files = $method->invoke($command, $tmpDir);

            $this->assertContains($tmpDir . '/keep.txt', $files);
            $this->assertNotContains($tmpDir . '/MANIFEST.sha256', $files);
        } finally {
            @unlink($tmpDir . '/MANIFEST.sha256');
            @unlink($tmpDir . '/keep.txt');
            @rmdir($tmpDir);
        }
    }
}
