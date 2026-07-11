<?php

namespace Modules\TitanCore\Console\Commands\Mdk;

/**
 * Scaffold a Titan Filament panel / page.
 *
 * Usage:
 *   php artisan titan:make-panel Admin
 *   php artisan titan:make-panel Admin --module=TitanAdmin
 */
class MakePanelCommand extends MdkBaseCommand
{
    protected $signature = 'titan:make-panel
                            {name : Panel name in PascalCase (e.g. Admin)}
                            {--module= : Module to create the panel in (defaults to name)}
                            {--dry-run : Preview files without writing}
                            {--force : Overwrite existing files}';

    protected $description = 'Scaffold a Titan Filament panel page class.';

    public function handle(): int
    {
        $name   = $this->toPascal((string) $this->argument('name'));
        $module = $this->option('module') ? $this->toPascal((string) $this->option('module')) : $name;
        $vars   = $this->buildVars($name, $module);

        $isDryRun = (bool) $this->option('dry-run');
        $this->components->info(($isDryRun ? '[DRY-RUN] ' : '')."Creating panel <fg=cyan>{$name}Panel</> in module <fg=cyan>{$module}</>");
        $this->newLine();

        $root = $this->modulesPath($module);

        $this->writeFile(
            $root."/Filament/Pages/{$name}Panel.php",
            $this->renderStub('Panel.php.stub', $vars)
        );

        $this->printSummary();

        return self::SUCCESS;
    }
}
