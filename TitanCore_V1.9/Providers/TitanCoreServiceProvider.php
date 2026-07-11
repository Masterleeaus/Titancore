<?php

namespace Modules\TitanCore\Providers;

use App\Http\Middleware\SuperAdmin;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\TitanCore\AI\VectorStore\VectorStoreFactory;
use Modules\TitanCore\Contracts\AI\VectorStoreContract;
use Modules\TitanCore\AI\ManifestValidator;
use Modules\TitanCore\Console\Commands\GenerateManifestCommand;
use Modules\TitanCore\Console\Commands\ModulesBlueprintDoctorCommand;
use Modules\TitanCore\Console\Commands\ModulesDepsCommand;
use Modules\TitanCore\Console\Commands\ModulesDisableCommand;
use Modules\TitanCore\Console\Commands\ModulesDoctorCommand;
use Modules\TitanCore\Console\Commands\ModulesEnableCommand;
use Modules\TitanCore\Console\Commands\ModulesHealthCommand;
use Modules\TitanCore\Console\Commands\ModulesManifestCacheCommand;
use Modules\TitanCore\Console\Commands\ModulesSchemaDocs;
use Modules\TitanCore\Console\Commands\ModulesStatusCommand;
use Modules\TitanCore\Console\Commands\ModulesUpgradeCommand;
use Modules\TitanCore\Console\Commands\SyncTitanDocsKnowledgeCommand;
use Modules\TitanCore\Console\Commands\ValidateManifestsCommand;
use Modules\TitanCore\Console\Commands\VerifyManifestCommand;
use Modules\TitanCore\Console\Commands\Mdk\MakeAgentCommand;
use Modules\TitanCore\Console\Commands\Mdk\MakeApiCommand;
use Modules\TitanCore\Console\Commands\Mdk\MakeContractCommand;
use Modules\TitanCore\Console\Commands\Mdk\MakeDtoCommand;
use Modules\TitanCore\Console\Commands\Mdk\MakeEventCommand;
use Modules\TitanCore\Console\Commands\Mdk\MakeModuleCommand;
use Modules\TitanCore\Console\Commands\Mdk\MakePanelCommand;
use Modules\TitanCore\Console\Commands\Mdk\MakePluginCommand;
use Modules\TitanCore\Console\Commands\Mdk\MakePolicyCommand;
use Modules\TitanCore\Console\Commands\Mdk\MakePromptCommand;
use Modules\TitanCore\Console\Commands\Mdk\MakeProviderCommand;
use Modules\TitanCore\Console\Commands\Mdk\MakeRegistryCommand;
use Modules\TitanCore\Console\Commands\Mdk\MakeToolCommand;
use Modules\TitanCore\Console\Commands\Mdk\MakeWidgetCommand;
use Modules\TitanCore\Console\Commands\Mdk\MakeWorkflowCommand;
use Modules\TitanCore\Console\Commands\Mdk\MigrateSdkCommand;
use Modules\TitanCore\Console\Commands\Mdk\TitanDoctorCommand;
use Modules\TitanCore\Console\Commands\Mdk\ValidateModuleCommand;
use Modules\TitanCore\Console\SyncTitanEchoAssistCommand;
use Modules\TitanCore\Services\Providers\TitanAiProvider;
use Modules\TitanCore\Services\Providers\TitanCoreAiProvider;
use Modules\TitanCore\Services\TitanAiClient;
use Modules\TitanCore\Services\TitanCoreAiClient;
use Modules\TitanCore\Services\TitanCoreModelGateway;
use Modules\TitanCore\Services\TitanCoreRouter;
use Modules\TitanCore\Support\ModuleDependencyGraph;

class TitanCoreServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->validateTitanConfig();

        $this->registerTranslations();
        $this->registerViews();

        // Migrations
        $migrationsPath = module_path('TitanCore').'/Database/Migrations';
        if (is_dir($migrationsPath)) {
            $this->loadMigrationsFrom($migrationsPath);
        }

        // Super Admin lock middleware
        $router = $this->app->make(Router::class);
        $router->aliasMiddleware('titancore.superadmin', SuperAdmin::class);
        // AI policy middleware — previously registered only in AIServiceProvider
        $router->aliasMiddleware('ai.policy', \Modules\TitanCore\Http\Middleware\CheckAiPolicy::class);

        // Web routes
        $web = __DIR__.'/../Routes/web.php';
        if (file_exists($web)) {
            Route::middleware('web')->group($web);
        }

        // API routes (mounted under /api)
        $api = __DIR__.'/../Routes/api.php';
        if (file_exists($api)) {
            Route::middleware('api')->prefix('api')->group($api);
        }

        // Console commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                SyncTitanDocsKnowledgeCommand::class,
                SyncTitanEchoAssistCommand::class,
                ModulesUpgradeCommand::class,
                ModulesHealthCommand::class,
                ModulesDoctorCommand::class,
                ModulesBlueprintDoctorCommand::class,
                ModulesManifestCacheCommand::class,
                ModulesStatusCommand::class,
                ModulesDepsCommand::class,
                ModulesEnableCommand::class,
                ModulesDisableCommand::class,
                ModulesSchemaDocs::class,
                ValidateManifestsCommand::class,
                GenerateManifestCommand::class,
                VerifyManifestCommand::class,
                // MDK — Module Developer Kit
                MakeModuleCommand::class,
                MakePluginCommand::class,
                MakeProviderCommand::class,
                MakeToolCommand::class,
                MakeAgentCommand::class,
                MakeWorkflowCommand::class,
                MakePromptCommand::class,
                MakeWidgetCommand::class,
                MakePanelCommand::class,
                MakeApiCommand::class,
                MakeEventCommand::class,
                MakeContractCommand::class,
                MakeDtoCommand::class,
                MakePolicyCommand::class,
                MakeRegistryCommand::class,
                TitanDoctorCommand::class,
                ValidateModuleCommand::class,
                MigrateSdkCommand::class,
            ]);
        }

        // Boot-time AI manifest validation — logs critical on failure.
        $this->booted(function () {
            if ($this->app->runningInConsole()) {
                return;
            }

            try {
                (new ManifestValidator())->bootCheck();
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::critical('TitanCore.ManifestValidator: boot check threw an exception', [
                    'error' => $e->getMessage(),
                ]);
            }
        });
    }

    public function register(): void
    {
        // App-level Titan config files (config/ directory)
        $this->mergeConfigFrom(config_path('titan-modules.php'), 'titan-modules');
        $this->mergeConfigFrom(config_path('titan-ai.php'), 'titan-ai');
        // The file name stays hyphenated for Laravel's config_path() convention,
        // but the runtime key remains underscored to preserve existing
        // config('titan_model_runtime.*') consumers.
        $this->mergeConfigFrom(config_path('titan-model-runtime.php'), 'titan_model_runtime');

        // Module-level config overrides
        $this->mergeConfigFrom(__DIR__.'/../Config/config.php', 'titancore');
        $this->mergeConfigFrom(__DIR__.'/../Config/titan_agents.php', 'titan_agents');
        $this->mergeConfigFrom(__DIR__.'/../Config/titan-model-runtime.php', 'titan_model_runtime');

        // AI sub-configs — previously merged only when AIServiceProvider was loaded.
        // Consolidated here so they are always available regardless of whether
        // AIServiceProvider is registered by the host application.
        //
        // ai.php is merged into 'titancore' (not 'titancore.ai') to preserve
        // backward-compatibility with consumers that reference keys such as
        // config('titancore.providers.openai.api_key') or config('titancore.default').
        // Laravel's mergeConfigFrom only fills missing keys, so this cannot overwrite
        // values set by config.php which is merged into the same 'titancore' namespace.
        $this->mergeConfigFrom(__DIR__.'/../Config/ai.php', 'titancore');
        $this->mergeConfigFrom(__DIR__.'/../Config/tools.php', 'titancore.tools');
        $this->mergeConfigFrom(__DIR__.'/../Config/permissions.php', 'titancore.permissions');
        $this->mergeConfigFrom(__DIR__.'/../Config/policies.php', 'titancore.policies');
        $this->mergeConfigFrom(__DIR__.'/../Config/metrics.php', 'titancore.metrics');

        // Bind canonical TitanCore AI client/provider + legacy aliases.
        $this->app->singleton(TitanCoreAiClient::class);
        $this->app->singleton(TitanAiClient::class, fn ($app) => $app->make(TitanCoreAiClient::class));
        $this->app->singleton(TitanCoreAiProvider::class);
        $this->app->singleton(TitanAiProvider::class, fn ($app) => $app->make(TitanCoreAiProvider::class));
        $this->app->singleton(TitanCoreRouter::class);

        // Model runtime gateway + failover chain
        $this->app->singleton(TitanCoreModelGateway::class);

        // no bindings; keep lightweight
        $this->app->singleton(
            ModuleDependencyGraph::class,
            fn ($app) => new ModuleDependencyGraph(
                $app['modules']
            )
        );

        // Vector store backend — resolved from config('titan-ai.vector_store.driver')
        $this->app->singleton(
            VectorStoreContract::class,
            fn ($app) => VectorStoreFactory::make($app),
        );
    }

    public function registerViews(): void
    {
        $viewPath = resource_path('views/modules/titancore');
        $sourcePath = module_path('TitanCore').'/Resources/views';

        if (is_dir($sourcePath)) {
            $this->publishes([
                $sourcePath => $viewPath,
            ], 'views');

            $this->loadViewsFrom($this->getPublishableViewPaths($sourcePath), 'titancore');
        }
    }

    public function registerTranslations(): void
    {
        $langPath = resource_path('lang/modules/titancore');

        if (is_dir($langPath)) {
            $this->loadTranslationsFrom($langPath, 'titancore');
        } else {
            $moduleLang = module_path('TitanCore').'/Resources/lang';
            if (is_dir($moduleLang)) {
                $this->loadTranslationsFrom($moduleLang, 'titancore');
            }
        }
    }

    private function getPublishableViewPaths(string $sourcePath): array
    {
        $paths = [];

        foreach (config('view.paths') as $path) {
            $candidate = $path.'/modules/titancore';

            if (is_dir($candidate)) {
                $paths[] = $candidate;
            }
        }

        $paths[] = $sourcePath;

        return $paths;
    }

    /**
     * Validate that all required Titan config keys are present.
     * Throws a RuntimeException immediately so the application fails loudly
     * rather than producing silent "Undefined array key" errors at runtime.
     */
    private function validateTitanConfig(): void
    {
        $missing = [];

        // titan-modules: path must be a non-empty string
        if (empty(config('titan-modules.path'))) {
            $missing[] = 'titan-modules.path';
        }

        // titan-ai: default_provider must be a non-empty string
        if (empty(config('titan-ai.default_provider'))) {
            $missing[] = 'titan-ai.default_provider';
        }

        // titan-model-runtime: providers must be a non-empty array
        $providers = config('titan_model_runtime.providers');
        if (empty($providers) || ! is_array($providers)) {
            $missing[] = 'titan_model_runtime.providers';
        }

        if (! empty($missing)) {
            throw new \RuntimeException(
                'Titan configuration is incomplete. Missing required keys: '.implode(', ', $missing).'. '
                .'Check your .env file and ensure config/titan-modules.php, config/titan-ai.php, '
                .'and config/titan-model-runtime.php are present.'
            );
        }
    }
}
