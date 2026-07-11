<?php

namespace Modules\TitanCore\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;

class TitanCoreServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Views (support both Resources/ and resources/ casing)
        $viewsA = __DIR__ . '/../Resources/views';
        $viewsB = __DIR__ . '/../resources/views';

        if (is_dir($viewsA)) {
            $this->loadViewsFrom($viewsA, 'titancore');
        } elseif (is_dir($viewsB)) {
            $this->loadViewsFrom($viewsB, 'titancore');
        }

        // Routes: avoid boot-fatal error if the file doesn't exist.
        $accountRoutes = __DIR__ . '/../routes/account.php';
        $webCandidates = [
            __DIR__ . '/../Routes/web.php',
            __DIR__ . '/../routes/web.php',
        ];

        if (file_exists($accountRoutes)) {
            // Keep the original intended prefix + name group for account routes
            Route::middleware(['web', 'auth'])
                ->prefix('account/titan/core')
                ->name('titan.core.')
                ->group($accountRoutes);

            return;
        }

        // Fallback: register standard module web routes if present.
        foreach ($webCandidates as $webRoutes) {
            if (file_exists($webRoutes)) {
                Route::middleware(['web', 'auth'])->group($webRoutes);
                return;
            }
        }

        // No routes file found — safely do nothing (prevents app-wide boot failure).
    }
}
