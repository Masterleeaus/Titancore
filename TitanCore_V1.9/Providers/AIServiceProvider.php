<?php

namespace Modules\TitanCore\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * AIServiceProvider
 *
 * @deprecated All responsibilities of this provider have been absorbed into
 *             TitanCoreServiceProvider (config merges, ai.policy middleware alias).
 *             This class is retained solely for backwards compatibility with host
 *             applications that still auto-load it explicitly via config/app.php.
 *             It will be removed in a future cleanup pass once all consumers are
 *             confirmed to have migrated.
 *
 * Boot-time registration of routes, migrations, views, and console commands is
 * handled exclusively by TitanCoreServiceProvider to prevent duplicate loading.
 */
class AIServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // These merges are now also performed in TitanCoreServiceProvider::register()
        // and are therefore always available. Retained here in case a host application
        // explicitly registers this provider — repeated mergeConfigFrom calls are safe
        // because Laravel's implementation only fills in missing keys.
        $this->mergeConfigFrom(__DIR__.'/../Config/ai.php', 'titancore');
        $this->mergeConfigFrom(__DIR__.'/../Config/tools.php', 'titancore.tools');
        $this->mergeConfigFrom(__DIR__.'/../Config/permissions.php', 'titancore.permissions');
        $this->mergeConfigFrom(__DIR__.'/../Config/policies.php', 'titancore.policies');
        $this->mergeConfigFrom(__DIR__.'/../Config/metrics.php', 'titancore.metrics');
    }

    public function boot(): void
    {
        // Route, migration, view, and command registration is handled
        // exclusively by TitanCoreServiceProvider to prevent duplicate loading.
    }
}
