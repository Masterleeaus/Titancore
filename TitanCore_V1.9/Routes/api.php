<?php

use Illuminate\Support\Facades\Route;
use Modules\TitanCore\Http\Controllers\Api\ChatApiController;
use Modules\TitanCore\Http\Controllers\Api\KbApiController;
use Modules\TitanCore\Http\Controllers\Api\MetricsController;
use Modules\TitanCore\Http\Controllers\Api\PlatformHealthController;
use Modules\TitanCore\Http\Controllers\Api\PromptApiController;
use Modules\TitanCore\Http\Controllers\Api\TitanAiProxyController;
use Modules\TitanCore\Http\Controllers\Api\ToolsApiController;
use Modules\TitanCore\Http\Controllers\HealthController;
use Modules\TitanCore\Http\Controllers\Api\V1\AgentsController;
use Modules\TitanCore\Http\Controllers\Api\V1\CompatibilityController;
use Modules\TitanCore\Http\Controllers\Api\V1\DiagnosticsController;
use Modules\TitanCore\Http\Controllers\Api\V1\DiscoveryController;
use Modules\TitanCore\Http\Controllers\Api\V1\KnowledgeController;
use Modules\TitanCore\Http\Controllers\Api\V1\MarketplaceController;
use Modules\TitanCore\Http\Controllers\Api\V1\ModulesController;
use Modules\TitanCore\Http\Controllers\Api\V1\PlatformController;
use Modules\TitanCore\Http\Controllers\Api\V1\ProvidersController;
use Modules\TitanCore\Http\Controllers\Api\V1\RegistryController;
use Modules\TitanCore\Http\Controllers\Api\V1\SdkController;
use Modules\TitanCore\Http\Controllers\Api\V1\TelemetryController;
use Modules\TitanCore\Http\Controllers\Api\V1\ToolsController;
use Modules\TitanCore\Http\Controllers\Api\V1\UpgradesController;
use Modules\TitanCore\Http\Controllers\Api\V1\WorkflowsController;

/*
|--------------------------------------------------------------------------
| TitanCore API Routes (Super Admin only)
|--------------------------------------------------------------------------
*/

Route::prefix('titancore')
    ->middleware(['auth', 'super-admin'])
    ->as('titancore.api.')
    ->group(function () {

        Route::get('/status', [HealthController::class, 'status'])->name('status');

        Route::get('/prompts', [PromptApiController::class, 'index'])->name('prompts.index');
        Route::get('/prompts/{id}', [PromptApiController::class, 'show'])->name('prompts.show');
        Route::post('/prompts', [PromptApiController::class, 'store'])->name('prompts.store');
        Route::put('/prompts/{id}', [PromptApiController::class, 'update'])->name('prompts.update');
        Route::delete('/prompts/{id}', [PromptApiController::class, 'destroy'])->name('prompts.destroy');

        Route::post('/tools/invoke', [ToolsApiController::class, 'invoke'])->name('tools.invoke');

        Route::post('/kb/ingest', [KbApiController::class, 'ingest'])->name('kb.ingest');
        Route::get('/kb/search', [KbApiController::class, 'search'])->name('kb.search');

        Route::post('/chat', [ChatApiController::class, 'chat'])->name('chat');

        Route::get('/usage', [MetricsController::class, 'usage'])->name('usage');
        Route::get('/metrics', [MetricsController::class, 'metrics'])->name('metrics');

        // titanai passthrough (expose all titanai features)
        Route::get('/titanai/ping', [TitanAiProxyController::class, 'ping'])->name('titanai.ping');
        Route::match(['GET','POST','PUT','PATCH','DELETE'], '/titanai/proxy', [TitanAiProxyController::class, 'proxy'])->name('titanai.proxy');
        Route::match(['GET','POST','PUT','PATCH','DELETE'], '/titanai/proxy/{any}', [TitanAiProxyController::class, 'proxy'])
            ->where('any', '.*')
            ->name('titanai.proxy.any');

});

/*
|--------------------------------------------------------------------------
| Platform Health Endpoint — /api/v1/platform/health
|--------------------------------------------------------------------------
| Authenticated, super-admin only.
| Returns per-check status (ok/warning/critical) for all platform layers.
*/
Route::prefix('v1/platform')
    ->middleware(['auth', 'super-admin'])
    ->as('titancore.platform.')
    ->group(function () {
        Route::get('/health', PlatformHealthController::class)->name('health');
    });

/*
|--------------------------------------------------------------------------
| TitanCore Platform API v1 — /api/v1/*
|--------------------------------------------------------------------------
| All routes require authentication and super-admin privilege.
| Versioned, contract-driven, no internal implementation exposed.
*/
Route::prefix('v1')
    ->middleware(['auth', 'super-admin'])
    ->as('titancore.v1.')
    ->group(function () {

        // ── Phase 1: Platform Dashboard & Info API ─────────────────────────
        Route::prefix('platform')->as('platform.')->group(function () {
            Route::get('/dashboard', [PlatformController::class, 'dashboard'])->name('dashboard');
            Route::get('/info',      [PlatformController::class, 'info'])->name('info');
            Route::get('/status',    [PlatformController::class, 'status'])->name('status');
            Route::get('/version',   [PlatformController::class, 'version'])->name('version');
            Route::get('/config',    [PlatformController::class, 'config'])->name('config');
            Route::get('/features',  [PlatformController::class, 'features'])->name('features');
            Route::get('/cache',     [PlatformController::class, 'cache'])->name('cache');
            Route::get('/telemetry', [PlatformController::class, 'telemetry'])->name('telemetry');
        });

        // ── Phase 2 & 3: Module Lifecycle & Dependency Graph API ──────────
        Route::prefix('modules')->as('modules.')->group(function () {
            Route::get('/',                    [ModulesController::class, 'index'])->name('index');
            Route::get('/discover',            [ModulesController::class, 'discover'])->name('discover');
            Route::get('/dependency-graph',    [ModulesController::class, 'dependencyGraph'])->name('dependency-graph');
            Route::post('/refresh',            [ModulesController::class, 'refresh'])->name('refresh');
            Route::post('/install',            [ModulesController::class, 'install'])->name('install');
            Route::post('/enable',             [ModulesController::class, 'enable'])->name('enable');
            Route::post('/disable',            [ModulesController::class, 'disable'])->name('disable');
            Route::post('/validate',           [ModulesController::class, 'validate'])->name('validate');
            Route::post('/repair',             [ModulesController::class, 'repair'])->name('repair');
            Route::post('/rebuild',            [ModulesController::class, 'rebuild'])->name('rebuild');
            Route::post('/update',             [ModulesController::class, 'update'])->name('update');
            Route::post('/rollback',           [ModulesController::class, 'rollback'])->name('rollback');
            Route::delete('/remove',           [ModulesController::class, 'remove'])->name('remove');
            Route::get('/{id}',                [ModulesController::class, 'show'])->name('show');
            Route::get('/{id}/dependencies',   [ModulesController::class, 'moduleDependencies'])->name('dependencies');
        });

        // ── Phase 4: Compatibility Manager API ────────────────────────────
        Route::prefix('compatibility')->as('compatibility.')->group(function () {
            Route::get('/',         [CompatibilityController::class, 'index'])->name('index');
            Route::get('/platform', [CompatibilityController::class, 'platform'])->name('platform');
            Route::get('/sdk',      [CompatibilityController::class, 'sdk'])->name('sdk');
            Route::get('/modules',  [CompatibilityController::class, 'modules'])->name('modules');
            Route::post('/check',   [CompatibilityController::class, 'check'])->name('check');
            Route::post('/warn',    [CompatibilityController::class, 'warn'])->name('warn');
        });

        // ── Phase 5: Registry Manager API ─────────────────────────────────
        Route::prefix('registry')->as('registry.')->group(function () {
            Route::get('/',           [RegistryController::class, 'index'])->name('index');
            Route::get('/modules',    [RegistryController::class, 'modules'])->name('modules');
            Route::get('/providers',  [RegistryController::class, 'providers'])->name('providers');
            Route::get('/tools',      [RegistryController::class, 'tools'])->name('tools');
            Route::get('/workflows',  [RegistryController::class, 'workflows'])->name('workflows');
            Route::get('/agents',     [RegistryController::class, 'agents'])->name('agents');
            Route::get('/prompts',    [RegistryController::class, 'prompts'])->name('prompts');
            Route::post('/rebuild',   [RegistryController::class, 'rebuild'])->name('rebuild');
            Route::post('/refresh',   [RegistryController::class, 'refresh'])->name('refresh');
            Route::get('/validate',   [RegistryController::class, 'validate'])->name('validate');
            Route::get('/export',     [RegistryController::class, 'export'])->name('export');
        });

        // ── Phase 6: Discovery Manager API ────────────────────────────────
        Route::prefix('discovery')->as('discovery.')->group(function () {
            Route::get('/assets',    [DiscoveryController::class, 'assets'])->name('assets');
            Route::get('/providers', [DiscoveryController::class, 'providers'])->name('providers');
            Route::get('/tools',     [DiscoveryController::class, 'tools'])->name('tools');
            Route::get('/workflows', [DiscoveryController::class, 'workflows'])->name('workflows');
            Route::get('/prompts',   [DiscoveryController::class, 'prompts'])->name('prompts');
            Route::get('/agents',    [DiscoveryController::class, 'agents'])->name('agents');
            Route::get('/manifests', [DiscoveryController::class, 'manifests'])->name('manifests');
        });

        // ── Phase 7: Provider API ─────────────────────────────────────────
        Route::prefix('providers')->as('providers.')->group(function () {
            Route::get('/',           [ProvidersController::class, 'index'])->name('index');
            Route::get('/models',     [ProvidersController::class, 'models'])->name('models');
            Route::get('/health',     [ProvidersController::class, 'health'])->name('health');
            Route::post('/test',      [ProvidersController::class, 'test'])->name('test');
            Route::get('/failover',   [ProvidersController::class, 'failover'])->name('failover');
            Route::post('/benchmark', [ProvidersController::class, 'benchmark'])->name('benchmark');
            Route::get('/{provider}', [ProvidersController::class, 'show'])->name('show');
        });

        // ── Phase 8: Knowledge API ────────────────────────────────────────
        Route::prefix('knowledge')->as('knowledge.')->group(function () {
            Route::get('/collections', [KnowledgeController::class, 'collections'])->name('collections');
            Route::get('/documents',   [KnowledgeController::class, 'documents'])->name('documents');
            Route::get('/chunks',      [KnowledgeController::class, 'chunks'])->name('chunks');
            Route::get('/embeddings',  [KnowledgeController::class, 'embeddings'])->name('embeddings');
            Route::get('/search',      [KnowledgeController::class, 'search'])->name('search');
            Route::post('/retrieve',   [KnowledgeController::class, 'retrieve'])->name('retrieve');
            Route::get('/citations',   [KnowledgeController::class, 'citations'])->name('citations');
            Route::post('/import',     [KnowledgeController::class, 'import'])->name('import');
            Route::get('/export',      [KnowledgeController::class, 'export'])->name('export');
        });

        // ── Phase 9: Tool Runtime API ─────────────────────────────────────
        Route::prefix('tools')->as('tools.')->group(function () {
            Route::get('/',           [ToolsController::class, 'index'])->name('index');
            Route::get('/discover',   [ToolsController::class, 'discover'])->name('discover');
            Route::post('/validate',  [ToolsController::class, 'validate'])->name('validate');
            Route::post('/execute',   [ToolsController::class, 'execute'])->name('execute');
            Route::get('/history',    [ToolsController::class, 'history'])->name('history');
            Route::get('/telemetry',  [ToolsController::class, 'telemetry'])->name('telemetry');
        });

        // ── Phase 10: Workflow API ────────────────────────────────────────
        Route::prefix('workflows')->as('workflows.')->group(function () {
            Route::get('/',           [WorkflowsController::class, 'index'])->name('index');
            Route::post('/run',       [WorkflowsController::class, 'run'])->name('run');
            Route::get('/status',     [WorkflowsController::class, 'status'])->name('status');
            Route::post('/pause',     [WorkflowsController::class, 'pause'])->name('pause');
            Route::post('/resume',    [WorkflowsController::class, 'resume'])->name('resume');
            Route::post('/cancel',    [WorkflowsController::class, 'cancel'])->name('cancel');
            Route::post('/replay',    [WorkflowsController::class, 'replay'])->name('replay');
            Route::get('/history',    [WorkflowsController::class, 'history'])->name('history');
        });

        // ── Phase 11: Agent API ───────────────────────────────────────────
        Route::prefix('agents')->as('agents.')->group(function () {
            Route::get('/',         [AgentsController::class, 'index'])->name('index');
            Route::post('/run',     [AgentsController::class, 'run'])->name('run');
            Route::get('/status',   [AgentsController::class, 'status'])->name('status');
            Route::get('/history',  [AgentsController::class, 'history'])->name('history');
            Route::get('/goals',    [AgentsController::class, 'goals'])->name('goals');
            Route::get('/plans',    [AgentsController::class, 'plans'])->name('plans');
            Route::get('/results',  [AgentsController::class, 'results'])->name('results');
        });

        // ── Phase 12: Telemetry & Observability API ───────────────────────
        Route::prefix('telemetry')->as('telemetry.')->group(function () {
            Route::get('/',           [TelemetryController::class, 'index'])->name('index');
            Route::get('/traces',     [TelemetryController::class, 'traces'])->name('traces');
            Route::get('/metrics',    [TelemetryController::class, 'metrics'])->name('metrics');
            Route::get('/costs',      [TelemetryController::class, 'costs'])->name('costs');
            Route::get('/errors',     [TelemetryController::class, 'errors'])->name('errors');
            Route::get('/providers',  [TelemetryController::class, 'providers'])->name('providers');
        });

        // ── Phase 13: Diagnostics & Developer Tools API ───────────────────
        Route::prefix('diagnostics')->as('diagnostics.')->group(function () {
            Route::get('/',                    [DiagnosticsController::class, 'index'])->name('index');
            Route::get('/system',              [DiagnosticsController::class, 'system'])->name('system');
            Route::get('/providers',           [DiagnosticsController::class, 'providers'])->name('providers');
            Route::get('/modules',             [DiagnosticsController::class, 'modules'])->name('modules');
            Route::get('/knowledge',           [DiagnosticsController::class, 'knowledge'])->name('knowledge');
            Route::get('/runtime',             [DiagnosticsController::class, 'runtime'])->name('runtime');
            Route::get('/storage',             [DiagnosticsController::class, 'storage'])->name('storage');
            // Developer Tools (Phase 11)
            Route::post('/doctor',             [DiagnosticsController::class, 'doctor'])->name('doctor');
            Route::post('/validate-manifests', [DiagnosticsController::class, 'validateManifests'])->name('validate-manifests');
            Route::get('/architecture',        [DiagnosticsController::class, 'architecture'])->name('architecture');
            Route::get('/contracts',           [DiagnosticsController::class, 'contracts'])->name('contracts');
        });

        // ── Phase 14: Upgrade Center API ──────────────────────────────────
        Route::prefix('upgrades')->as('upgrades.')->group(function () {
            Route::get('/',          [UpgradesController::class, 'index'])->name('index');
            Route::get('/check',     [UpgradesController::class, 'check'])->name('check');
            Route::post('/run',      [UpgradesController::class, 'run'])->name('run');
            Route::post('/validate', [UpgradesController::class, 'validate'])->name('validate');
            Route::post('/rollback', [UpgradesController::class, 'rollback'])->name('rollback');
            Route::get('/history',   [UpgradesController::class, 'history'])->name('history');
            Route::post('/verify',   [UpgradesController::class, 'verify'])->name('verify');
        });

        // ── Phase 15: Marketplace Infrastructure API ───────────────────────
        Route::prefix('marketplace')->as('marketplace.')->group(function () {
            Route::get('/',               [MarketplaceController::class, 'index'])->name('index');
            Route::get('/packages',       [MarketplaceController::class, 'packages'])->name('packages');
            Route::get('/publishers',     [MarketplaceController::class, 'publishers'])->name('publishers');
            Route::post('/verify',        [MarketplaceController::class, 'verify'])->name('verify');
            Route::post('/compatibility', [MarketplaceController::class, 'compatibility'])->name('compatibility');
            Route::post('/resolve',       [MarketplaceController::class, 'resolve'])->name('resolve');
        });

        // ── Phase 16: SDK Metadata API ────────────────────────────────────
        Route::prefix('sdk')->as('sdk.')->group(function () {
            Route::get('/contracts',    [SdkController::class, 'contracts'])->name('contracts');
            Route::get('/events',       [SdkController::class, 'events'])->name('events');
            Route::get('/manifests',    [SdkController::class, 'manifests'])->name('manifests');
            Route::get('/version',      [SdkController::class, 'version'])->name('version');
            Route::get('/capabilities', [SdkController::class, 'capabilities'])->name('capabilities');
        });
    });
