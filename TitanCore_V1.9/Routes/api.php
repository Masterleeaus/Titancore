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
