<?php

namespace Modules\TitanCore\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * AIServiceProvider
 *
 * Merges AI-specific sub-configuration namespaces that are not loaded by
 * TitanCoreServiceProvider.  Routes, migrations, views, and console commands
 * are all handled by TitanCoreServiceProvider; this provider covers only
 * the supplementary config keys consumed by middleware and the tool registry.
 *
 * @deprecated Not registered via module.json or composer.json extras.
 *             The config merges below have been absorbed into
 *             TitanCoreServiceProvider::register().  This class will be
 *             removed in a future cleanup pass once all consumers are verified.
 */
class AIServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // These merges are now also performed in TitanCoreServiceProvider.
        // Retained here for any host applications that still auto-load this
        // provider explicitly via config/app.php.
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
