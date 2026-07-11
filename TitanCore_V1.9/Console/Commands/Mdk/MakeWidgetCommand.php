<?php

namespace Modules\TitanCore\Console\Commands\Mdk;

/**
 * Scaffold a Titan Filament widget.
 *
 * Usage:
 *   php artisan titan:make-widget DashboardCard
 *   php artisan titan:make-widget DashboardCard --module=Admin
 */
class MakeWidgetCommand extends MdkBaseCommand
{
    protected $signature = 'titan:make-widget
                            {name : Widget name in PascalCase (e.g. DashboardCard)}
                            {--module= : Module to create the widget in (defaults to name)}
                            {--dry-run : Preview files without writing}
                            {--force : Overwrite existing files}';

    protected $description = 'Scaffold a Titan Filament widget class.';

    public function handle(): int
    {
        $name   = $this->toPascal((string) $this->argument('name'));
        $module = $this->option('module') ? $this->toPascal((string) $this->option('module')) : $name;
        $vars   = $this->buildVars($name, $module);

        $isDryRun = (bool) $this->option('dry-run');
        $this->components->info(($isDryRun ? '[DRY-RUN] ' : '')."Creating widget <fg=cyan>{$name}Widget</> in module <fg=cyan>{$module}</>");
        $this->newLine();

        $root = $this->modulesPath($module);

        $this->writeFile(
            $root."/Filament/Widgets/{$name}Widget.php",
            $this->renderStub('Widget.php.stub', $vars)
        );

        $this->printSummary();

        return self::SUCCESS;
    }
}
