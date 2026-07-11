<?php

namespace TitanSDK\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\TitanCore\Services\Engine\EngineManager;
use Modules\TitanCore\Services\TitanCoreAIService;
use Modules\TitanCore\Services\TitanCoreModelGateway;
use TitanSDK\Services\TitanAIManager;
use TitanSDK\Services\TitanEngineManager;

class TitanSdkServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/titansdk.php', 'titansdk');

        $this->app->singleton('titansdk.ai', function ($app): TitanAIManager {
            return new TitanAIManager(
                $app->make(TitanCoreAIService::class),
                $app->make(TitanCoreModelGateway::class),
            );
        });
        $this->app->singleton('titansdk.engine', fn ($app): TitanEngineManager => new TitanEngineManager(
            $app->make(EngineManager::class),
        ));

        $this->app->alias('titansdk.ai', TitanAIManager::class);
        $this->app->alias('titansdk.engine', TitanEngineManager::class);
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../../config/titansdk.php' => config_path('titansdk.php'),
        ], 'titansdk-config');

        $this->publishes([
            __DIR__ . '/../../manifests' => resource_path('titansdk/manifests'),
        ], 'titansdk-manifests');
    }
}
