<?php
namespace Modules\TitanCore\Providers;

use Illuminate\Support\ServiceProvider;

class AIServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../Config/ai.php', 'titancore');
        $this->mergeConfigFrom(__DIR__.'/../Config/tools.php', 'titancore.tools');
        $this->mergeConfigFrom(__DIR__.'/../Config/permissions.php', 'titancore.permissions');
        $this->mergeConfigFrom(__DIR__.'/../Config/policies.php', 'titancore.policies');
        $this->mergeConfigFrom(__DIR__.'/../Config/metrics.php', 'titancore.metrics');
    }

    public function boot()
    {
        $this->loadRoutesFrom(__DIR__.'/../Routes/web.php');
        $this->loadRoutesFrom(__DIR__.'/../Routes/api.php');
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'titancore');

        // alias tenant model policy middleware if available
        if ($this->app->bound('router')) {
            $this->app['router']->aliasMiddleware('ai.policy', \Modules\TitanCore\Http\Middleware\CheckAiPolicy::class);
        }

        if ($this->app->runningInConsole()) {
            $this->commands([
                \Modules\TitanCore\Console\AiSmokeCommand::class,
                \Modules\TitanCore\Console\UninstallCommand::class,
            ]);
            $this->publishes([
                __DIR__.'/../Resources/views/components/create-with-ai.blade.php' =>
                    resource_path('views/vendor/titancore/components/create-with-ai.blade.php'),
            ], 'titancore-ui');
        }
    }
}