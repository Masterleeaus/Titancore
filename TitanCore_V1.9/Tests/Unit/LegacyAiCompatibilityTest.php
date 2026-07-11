<?php

namespace Modules\TitanCore\Tests\Unit;

use PHPUnit\Framework\TestCase;

class LegacyAiCompatibilityTest extends TestCase
{
    public function test_service_provider_binds_magic_ai_aliases_to_canonical_services(): void
    {
        $provider = file_get_contents(__DIR__ . '/../../Providers/TitanCoreServiceProvider.php');

        $this->assertIsString($provider);
        $this->assertStringContainsString(
            "singleton(MagicAiClient::class, fn (\$app) => \$app->make(TitanCoreAiClient::class));",
            $provider,
        );
        $this->assertStringContainsString(
            "singleton(MagicAiProvider::class, fn (\$app) => \$app->make(TitanCoreAiProvider::class));",
            $provider,
        );
    }

    public function test_router_delegates_legacy_ai_requests_through_the_gateway(): void
    {
        $router = file_get_contents(__DIR__ . '/../../Services/TitanCoreRouter.php');

        $this->assertIsString($router);
        $this->assertStringContainsString('protected TitanCoreModelGateway $gateway', $router);
        $this->assertStringContainsString('$result = $this->gateway->invokeTool($request, $runtimeConfig, [', $router);
    }

    public function test_api_routes_keep_titanai_canonical_and_magicai_compatibility_endpoints(): void
    {
        $routes = file_get_contents(__DIR__ . '/../../Routes/api.php');

        $this->assertIsString($routes);
        $this->assertStringContainsString("Route::get('/titanai/ping'", $routes);
        $this->assertStringContainsString("Route::match(['GET','POST','PUT','PATCH','DELETE'], '/titanai/proxy'", $routes);
        $this->assertStringContainsString("Route::get('/magicai/ping'", $routes);
        $this->assertStringContainsString("Route::match(['GET','POST','PUT','PATCH','DELETE'], '/magicai/proxy'", $routes);
    }

    public function test_web_routes_keep_magicai_console_and_launcher_aliases(): void
    {
        $routes = file_get_contents(__DIR__ . '/../../Routes/web.php');

        $this->assertIsString($routes);
        $this->assertStringContainsString("Route::get('/titanai', [TitanAiConsoleController::class, 'index'])", $routes);
        $this->assertStringContainsString("Route::get('/magicai', [MagicAiConsoleController::class, 'index'])", $routes);
        $this->assertStringContainsString("Route::get('/titanai', [TitanAiLauncherController::class, 'index'])", $routes);
        $this->assertStringContainsString("Route::get('/magicai', [MagicAiLauncherController::class, 'index'])", $routes);
    }

    public function test_magic_compatibility_controllers_delegate_to_canonical_controllers(): void
    {
        $adminController = file_get_contents(__DIR__ . '/../../Http/Controllers/Admin/MagicAiConsoleController.php');
        $tenantController = file_get_contents(__DIR__ . '/../../Http/Controllers/Tenant/MagicAiLauncherController.php');
        $apiController = file_get_contents(__DIR__ . '/../../Http/Controllers/Api/MagicAiProxyController.php');

        $this->assertIsString($adminController);
        $this->assertIsString($tenantController);
        $this->assertIsString($apiController);
        $this->assertStringContainsString('class MagicAiConsoleController extends TitanAiConsoleController', $adminController);
        $this->assertStringContainsString('class MagicAiLauncherController extends TitanAiLauncherController', $tenantController);
        $this->assertStringContainsString('class MagicAiProxyController extends TitanAiProxyController', $apiController);
    }
}
