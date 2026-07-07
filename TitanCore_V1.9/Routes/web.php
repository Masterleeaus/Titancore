<?php

use Illuminate\Support\Facades\Route;
use Modules\TitanCore\Http\Controllers\Tenant\TitanAiLauncherController;
use Modules\TitanCore\Http\Controllers\Admin\TitanAiConsoleController;
use Modules\TitanCore\Http\Controllers\PromptController;
use Modules\TitanCore\Http\Controllers\HealthController;
use Modules\TitanCore\Http\Controllers\Admin\DashboardController;
use Modules\TitanCore\Http\Controllers\Admin\SettingsController;
use Modules\TitanCore\Http\Controllers\Admin\UsageController;

/*
|--------------------------------------------------------------------------
| TitanCore Web Routes (Split Access)
|--------------------------------------------------------------------------
| Read-only: can:view_ai_usage
| Admin:     can:manage_ai
*/
Route::prefix('titancore')
    ->middleware(['web','auth','super-admin'])
    ->group(function () {

        Route::get('/health', [HealthController::class, 'index'])
            ->name('titancore.health');

        Route::get('/dashboard', [DashboardController::class, 'index'])
            ->name('titancore.dashboard.index');

        Route::get('/usage', [UsageController::class, 'index'])
            ->name('titancore.usage.index');

        Route::get('/prompts', [PromptController::class, 'index'])
            ->name('titancore.prompts.index');

        Route::get('/prompts/{id}/edit', [PromptController::class, 'edit'])
            ->name('titancore.prompts.edit');

        Route::get('/settings', [SettingsController::class, 'index'])
            ->name('titancore.settings.index');

        Route::post('/settings', [SettingsController::class, 'update'])
            ->name('titancore.settings.update');

        Route::post('/settings/sync-kb', [SettingsController::class, 'syncKb'])
            ->name('titancore.settings.sync_kb');
    });

// Superadmin (System) routes
Route::group([
    'prefix' => 'admin/settings/titancore',
    'middleware' => ['web', 'auth'],
], function () {
    Route::get('/titanai', [TitanAiConsoleController::class, 'index'])->name('titancore.admin.titanai.console');
});



// Tenant (Account) routes
Route::group([
    'prefix' => 'account/titancore',
    'middleware' => ['web', 'auth'],
], function () {
    Route::get('/titanai', [TitanAiLauncherController::class, 'index'])->name('titancore.tenant.titanai.launcher');
});



/*
|--------------------------------------------------------------------------
| PASS 5: Titan AI Settings (Admin)
|--------------------------------------------------------------------------
*/
Route::group(['middleware' => ['auth']], function () {
    Route::get('/titan-core/ai/settings', [\Modules\TitanCore\Http\Controllers\Admin\TitanAISettingsController::class, 'index'])->name('titan.core.ai.settings');
    Route::post('/titan-core/ai/settings/sync-agents', [\Modules\TitanCore\Http\Controllers\Admin\TitanAISettingsController::class, 'syncAgents'])->name('titan.core.ai.settings.sync_agents');
    Route::post('/titan-core/ai/settings/sync-titandocs', [\Modules\TitanCore\Http\Controllers\Admin\TitanAISettingsController::class, 'syncTitanDocs'])->name('titan.core.ai.settings.sync_titandocs');
    Route::post('/titan-core/ai/settings/save', [\Modules\TitanCore\Http\Controllers\Admin\TitanAISettingsController::class, 'save'])->name('titan.core.ai.settings.save');
});

Route::group(['middleware' => ['auth']], function () {
    Route::post('/titan-core/ai/settings/test-agent', [\Modules\TitanCore\Http\Controllers\Admin\TitanAISettingsController::class, 'testAgent'])->name('titan.core.ai.settings.test_agent');
    Route::post('/titan-core/ai/settings/re-embed-missing', [\Modules\TitanCore\Http\Controllers\Admin\TitanAISettingsController::class, 'reEmbedMissing'])->name('titan.core.ai.settings.re_embed_missing');
    Route::post('/titan-core/ai/settings/publish-kb-snapshot', [\Modules\TitanCore\Http\Controllers\Admin\TitanAISettingsController::class, 'publishKbSnapshot'])->name('titan.core.ai.settings.publish_kb_snapshot');
    Route::post('/titan-core/ai/settings/publish-agent-contract', [\Modules\TitanCore\Http\Controllers\Admin\TitanAISettingsController::class, 'publishAgentContract'])->name('titan.core.ai.settings.publish_agent_contract');

    // API Key management
    Route::post('/titan-core/ai/settings/save-api-keys', [\Modules\TitanCore\Http\Controllers\Admin\TitanAISettingsController::class, 'saveApiKeys'])->name('titan.core.ai.settings.save_api_keys');

    // Agent CRUD
    Route::get('/titan-core/ai/agents', [\Modules\TitanCore\Http\Controllers\Admin\AgentBuilderController::class, 'index'])->name('titan.core.ai.agents.index');
    Route::post('/titan-core/ai/agents', [\Modules\TitanCore\Http\Controllers\Admin\AgentBuilderController::class, 'store'])->name('titan.core.ai.agents.store');
    Route::get('/titan-core/ai/agents/{slug}/edit', [\Modules\TitanCore\Http\Controllers\Admin\AgentBuilderController::class, 'edit'])->name('titan.core.ai.agents.edit');
    Route::put('/titan-core/ai/agents/{slug}', [\Modules\TitanCore\Http\Controllers\Admin\AgentBuilderController::class, 'update'])->name('titan.core.ai.agents.update');
    Route::delete('/titan-core/ai/agents/{slug}', [\Modules\TitanCore\Http\Controllers\Admin\AgentBuilderController::class, 'destroy'])->name('titan.core.ai.agents.destroy');

    // Prompt storeVersion (was missing!)
    Route::post('/prompts/{namespace}/{slug}/version', [\Modules\TitanCore\Http\Controllers\PromptController::class, 'storeVersion'])->name('titancore.prompts.storeVersion');
});
