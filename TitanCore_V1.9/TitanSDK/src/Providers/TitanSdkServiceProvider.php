<?php

namespace TitanSDK\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\TitanCore\Services\TitanCoreAIService;
use Modules\TitanCore\Services\TitanCoreModelGateway;
use TitanSDK\Facades\TitanAI;

class TitanSdkServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/titansdk.php', 'titansdk');

        $this->app->singleton(TitanAI::class, function ($app): TitanAI {
            return new TitanAI(
                $app->make(TitanCoreAIService::class),
                $app->make(TitanCoreModelGateway::class),
            );
        });
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
