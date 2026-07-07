<?php

namespace Modules\TitanCore\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;

class TitanCoreServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Views (handle both Resources and resources casing)
        $viewsPathA = __DIR__ . '/../Resources/views';
        $viewsPathB = __DIR__ . '/../resources/views';
        if (is_dir($viewsPathA)) {
            $this->loadViewsFrom($viewsPathA, 'titancore');
        } elseif (is_dir($viewsPathB)) {
            $this->loadViewsFrom($viewsPathB, 'titancore');
        }

        // Routes: don't hard-require a missing file.
        // Prefer standard module route locations if present.
        $routesCandidates = [
            __DIR__ . '/../Routes/web.php',
            __DIR__ . '/../routes/web.php',
            __DIR__ . '/../Routes/account.php',
            __DIR__ . '/../routes/account.php',
        ];

        $routesFile = null;
        foreach ($routesCandidates as $candidate) {
            if (file_exists($candidate)) {
                $routesFile = $candidate;
                break;
            }
        }

        if ($routesFile) {
            // Let the routes file define its own prefix/name groups.
            Route::middleware(['web', 'auth'])->group($routesFile);
        }
    }
}
