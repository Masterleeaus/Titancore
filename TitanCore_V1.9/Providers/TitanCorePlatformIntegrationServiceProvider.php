<?php

namespace Modules\TitanCore\Providers;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Modules\TitanCore\Services\AssetDiscoveryService;
use Modules\TitanCore\Services\Engine\EngineDiscovery;
use Modules\TitanCore\Services\Engine\EngineInstaller;
use Modules\TitanCore\Services\Engine\EngineLifecycle;
use Modules\TitanCore\Services\Engine\EngineLoader;
use Modules\TitanCore\Services\Engine\EngineManager;
use Modules\TitanCore\Services\Engine\EngineRegistry;
use Modules\TitanCore\Services\Engine\EngineValidator;
use Modules\TitanCore\Services\TitanCoreAIService;
use Modules\TitanCore\Support\AssetManifestValidator;

/**
 * Registers TitanCore's AI assets with the Platform Manager.
 *
 * All registration is driven exclusively from metadata JSON files (asset.json,
 * provider.json, agent.json, tool.json, prompt.json, workflow.json).
 * No asset is hardcoded in PHP — the source of truth is the metadata.
 *
 * Discovery flow:
 *   1. AssetDiscoveryService scans the module's AI/ directory for JSON manifests.
 *   2. Each manifest is fully validated (schema version + required fields + item
 *      subfields) with AssetManifestValidator before use.
 *   3. Valid assets are registered with the Platform Manager's RegistryManager.
 *   4. The module itself is registered with ModuleManager using capabilities
 *      indexed across all manifests from asset.json rather than a hardcoded list.
 *
 * Graceful failure: missing, unreadable, or invalid manifests are logged
 * as warnings and skipped — they never crash the application boot.
 * The RegistryManager/ModuleManager binding is checked at runtime; if the
 * Platform Manager module is not installed, registration is silently skipped.
 */
class TitanCorePlatformIntegrationServiceProvider extends ServiceProvider
{
    /**
     * Register bindings in the service container.
     */
    public function register(): void
    {
        $this->app->singleton(TitanCoreAIService::class);

        $this->app->singleton(AssetDiscoveryService::class, fn () => new AssetDiscoveryService(
            new AssetManifestValidator()
        ));
        $this->app->singleton(EngineRegistry::class);
        $this->app->singleton(EngineLifecycle::class);
        $this->app->singleton(EngineLoader::class);
        $this->app->singleton(EngineInstaller::class);
        $this->app->singleton(EngineDiscovery::class);
        $this->app->singleton(EngineValidator::class, fn () => new EngineValidator(new AssetManifestValidator()));
        $this->app->singleton(EngineManager::class);
    }

    /**
     * Boot the application events.
     */
    public function boot(): void
    {
        $this->app->booted(function () {
            $this->registerAssetsFromMetadata();
        });
    }

    // ── Private ────────────────────────────────────────────────────────────────

    /**
     * Discover all assets from JSON metadata and register them with the Platform Manager.
     */
    private function registerAssetsFromMetadata(): void
    {
        // Guard: skip if Platform Manager interfaces are unavailable
        if (! $this->app->bound('Modules\Aitools\Services\Registry\RegistryManager')
            || ! $this->app->bound('Modules\Aitools\Services\Managers\ModuleManager')) {
            Log::debug('[TitanCore][PlatformIntegration] Platform Manager not bound — skipping asset registration.');

            return;
        }

        /** @var \Modules\Aitools\Services\Registry\RegistryManager $registry */
        $registry = $this->app->make('Modules\Aitools\Services\Registry\RegistryManager');
        /** @var \Modules\Aitools\Services\Managers\ModuleManager $moduleManager */
        $moduleManager = $this->app->make('Modules\Aitools\Services\Managers\ModuleManager');

        /** @var AssetDiscoveryService $discovery */
        $discovery = $this->app->make(AssetDiscoveryService::class);

        $moduleDir = $this->resolveModuleDir();

        $discovered = $discovery->discoverAll($moduleDir);

        // Log discovery errors as warnings — never throw
        foreach ($discovered['errors'] as $error) {
            Log::warning('[TitanCore][PlatformIntegration] Asset discovery error', ['error' => $error]);
        }

        // Index capabilities from all manifests for the module registration payload
        $capabilities = $discovery->indexCapabilities($discovered);

        // Register the module itself — capabilities come from aggregated metadata
        $this->registerModule($moduleManager, $discovered['asset'], $capabilities);

        // Register every provider from provider.json
        foreach ($discovered['providers'] as $provider) {
            $this->registerProvider($registry, $provider);
        }

        // Register every agent from agent.json
        foreach ($discovered['agents'] as $agent) {
            $this->registerAgent($registry, $agent);
        }

        // Register every tool from tool.json
        foreach ($discovered['tools'] as $tool) {
            $this->registerTool($registry, $tool);
        }

        // Register every prompt from prompt.json
        foreach ($discovered['prompts'] as $prompt) {
            $this->registerPrompt($registry, $prompt);
        }

        // Register every workflow from workflow.json
        foreach ($discovered['workflows'] as $workflow) {
            $this->registerWorkflow($registry, $workflow);
        }

        // Register every engine from engine.json
        foreach ($discovered['engines'] ?? [] as $engine) {
            $this->registerEngine($registry, $engine);
        }
    }

    /**
     * Register the TitanCore module itself with the ModuleManager.
     *
     * @param  mixed       $moduleManager
     * @param  array|null  $assetManifest   Parsed AI/asset.json data (null when missing).
     * @param  string[]    $capabilities    Indexed capability list from all manifests.
     */
    private function registerModule(mixed $moduleManager, ?array $assetManifest, array $capabilities): void
    {
        try {
            $moduleManager->registerModule([
                'name'         => 'TitanCore',
                'alias'        => 'titancore',
                'description'  => $assetManifest['description']
                    ?? 'Core AI infrastructure for the Titan Ecosystem',
                'version'      => $assetManifest['version'] ?? '1.9.0',
                'providers'    => [
                    'Modules\\TitanCore\\Providers\\TitanCoreServiceProvider',
                    'Modules\\TitanCore\\Providers\\TitanCorePlatformIntegrationServiceProvider',
                ],
                'capabilities' => $capabilities,
            ]);
        } catch (\Throwable $e) {
            Log::warning('[TitanCore][PlatformIntegration] Failed to register module', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Register a single provider entry from provider.json.
     *
     * @param  mixed  $registry
     * @param  array  $provider  One item from the 'providers' array.
     */
    private function registerProvider(mixed $registry, array $provider): void
    {
        try {
            $registry->registerAsset('provider', [
                'name'     => $provider['name'],
                'module'   => 'TitanCore',
                'metadata' => [
                    'id'            => $provider['id'] ?? null,
                    'description'   => $provider['description'],
                    'capabilities'  => $provider['capabilities'] ?? [],
                    'models'        => $provider['models'] ?? [],
                    'auth_type'     => $provider['authentication']['type'] ?? 'unknown',
                    'health_check'  => $provider['health_check'] ?? false,
                    'cost_tracking' => $provider['cost_tracking'] ?? [],
                    'failover'      => $provider['failover'] ?? [],
                    'rate_limits'   => $provider['rate_limits'] ?? [],
                    'class'         => $provider['class'] ?? null,
                    'tags'          => $provider['tags'] ?? [],
                ],
            ]);
        } catch (\Throwable $e) {
            Log::warning('[TitanCore][PlatformIntegration] Failed to register provider', [
                'provider' => $provider['id'] ?? $provider['name'] ?? '?',
                'error'    => $e->getMessage(),
            ]);
        }
    }

    /**
     * Register a single agent entry from agent.json.
     *
     * @param  mixed  $registry
     * @param  array  $agent
     */
    private function registerAgent(mixed $registry, array $agent): void
    {
        try {
            $registry->registerAsset('agent', [
                'name'     => $agent['name'],
                'module'   => 'TitanCore',
                'metadata' => [
                    'id'              => $agent['id'] ?? null,
                    'description'     => $agent['description'],
                    'capabilities'    => $agent['capabilities'] ?? [],
                    'assigned_tools'  => $agent['assigned_tools'] ?? [],
                    'prompt_library'  => $agent['prompt_library'] ?? [],
                    'memory'          => $agent['memory'] ?? 'stateless',
                    'retrieval'       => $agent['retrieval'] ?? 'none',
                    'policies'        => $agent['policies'] ?? [],
                    'class'           => $agent['class'] ?? null,
                    'tags'            => $agent['tags'] ?? [],
                ],
            ]);
        } catch (\Throwable $e) {
            Log::warning('[TitanCore][PlatformIntegration] Failed to register agent', [
                'agent' => $agent['id'] ?? $agent['name'] ?? '?',
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Register a single tool entry from tool.json.
     *
     * @param  mixed  $registry
     * @param  array  $tool
     */
    private function registerTool(mixed $registry, array $tool): void
    {
        try {
            $registry->registerAsset('tool', [
                'name'     => $tool['name'],
                'module'   => 'TitanCore',
                'metadata' => [
                    'id'           => $tool['id'] ?? null,
                    'description'  => $tool['description'],
                    'capabilities' => $tool['capabilities'] ?? [],
                    'parameters'   => $tool['parameters'] ?? [],
                    'risk_class'   => $tool['risk_class'] ?? 'read',
                    'class'        => $tool['class'] ?? null,
                    'permissions'  => $tool['permissions'] ?? [],
                    'tags'         => $tool['tags'] ?? [],
                ],
            ]);
        } catch (\Throwable $e) {
            Log::warning('[TitanCore][PlatformIntegration] Failed to register tool', [
                'tool'  => $tool['id'] ?? $tool['name'] ?? '?',
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Register a single prompt entry from prompt.json.
     *
     * @param  mixed  $registry
     * @param  array  $prompt
     */
    private function registerPrompt(mixed $registry, array $prompt): void
    {
        try {
            $registry->registerAsset('prompt', [
                'name'     => $prompt['name'],
                'module'   => 'TitanCore',
                'metadata' => [
                    'id'          => $prompt['id'] ?? null,
                    'description' => $prompt['description'],
                    'category'    => $prompt['category'] ?? 'general',
                    'variables'   => $prompt['variables'] ?? [],
                    'parameters'  => $prompt['parameters'] ?? [],
                    'validation'  => $prompt['validation'] ?? [],
                    'usage'       => $prompt['usage'] ?? '',
                    'tags'        => $prompt['tags'] ?? [],
                ],
            ]);
        } catch (\Throwable $e) {
            Log::warning('[TitanCore][PlatformIntegration] Failed to register prompt', [
                'prompt' => $prompt['id'] ?? $prompt['name'] ?? '?',
                'error'  => $e->getMessage(),
            ]);
        }
    }

    /**
     * Register a single workflow entry from workflow.json.
     *
     * @param  mixed  $registry
     * @param  array  $workflow
     */
    private function registerWorkflow(mixed $registry, array $workflow): void
    {
        try {
            $registry->registerAsset('workflow', [
                'name'     => $workflow['name'],
                'module'   => 'TitanCore',
                'metadata' => [
                    'id'           => $workflow['id'] ?? null,
                    'description'  => $workflow['description'],
                    'capabilities' => $workflow['capabilities'] ?? [],
                    'steps'        => $workflow['steps'] ?? [],
                    'class'        => $workflow['class'] ?? null,
                    'permissions'  => $workflow['permissions'] ?? [],
                    'tags'         => $workflow['tags'] ?? [],
                ],
            ]);
        } catch (\Throwable $e) {
            Log::warning('[TitanCore][PlatformIntegration] Failed to register workflow', [
                'workflow' => $workflow['id'] ?? $workflow['name'] ?? '?',
                'error'    => $e->getMessage(),
            ]);
        }
    }

    /**
     * Register a single engine entry from engine.json.
     *
     * @param  mixed  $registry
     * @param  array  $engine
     */
    private function registerEngine(mixed $registry, array $engine): void
    {
        try {
            $registry->registerAsset('engine', [
                'name'     => $engine['name'],
                'module'   => 'TitanCore',
                'metadata' => [
                    'id'           => $engine['id'] ?? null,
                    'description'  => $engine['description'] ?? '',
                    'class'        => $engine['class'] ?? null,
                    'version'      => $engine['version'] ?? null,
                    'lifecycle'    => $engine['lifecycle'] ?? 'managed',
                    'capabilities' => $engine['capabilities'] ?? [],
                    'permissions'  => $engine['permissions'] ?? [],
                    'tags'         => $engine['tags'] ?? [],
                ],
            ]);
        } catch (\Throwable $e) {
            Log::warning('[TitanCore][PlatformIntegration] Failed to register engine', [
                'engine' => $engine['id'] ?? $engine['name'] ?? '?',
                'error'  => $e->getMessage(),
            ]);
        }
    }

    /**
     * Resolve the absolute path to the TitanCore module directory.
     */
    private function resolveModuleDir(): string
    {
        // Support module_path() helper (nWidart/laravel-modules)
        if (function_exists('module_path')) {
            return module_path('TitanCore');
        }

        // Fallback: locate relative to this file (Providers/ is one level below the root)
        return dirname(__DIR__);
    }
}
