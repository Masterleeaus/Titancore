<?php

namespace Modules\TitanCore\Console\Commands\Mdk;

/**
 * Scaffold a Titan registry class.
 *
 * Usage:
 *   php artisan titan:make-registry CustomerRegistry
 *   php artisan titan:make-registry CustomerRegistry --module=CRM
 */
class MakeRegistryCommand extends MdkBaseCommand
{
    protected $signature = 'titan:make-registry
                            {name : Registry name in PascalCase (e.g. CustomerRegistry)}
                            {--module= : Module to create the registry in (defaults to name)}
                            {--dry-run : Preview files without writing}
                            {--force : Overwrite existing files}';

    protected $description = 'Scaffold a Titan registry class.';

    public function handle(): int
    {
        $name   = $this->toPascal((string) $this->argument('name'));
        $module = $this->option('module') ? $this->toPascal((string) $this->option('module')) : $name;
        $vars   = $this->buildVars($name, $module);

        $isDryRun = (bool) $this->option('dry-run');
        $this->components->info(($isDryRun ? '[DRY-RUN] ' : '')."Creating registry <fg=cyan>{$name}Registry</> in module <fg=cyan>{$module}</>");
        $this->newLine();

        $root = $this->modulesPath($module);

        $this->writeFile(
            $root."/Support/{$name}Registry.php",
            $this->renderStub('Registry.php.stub', $vars)
        );

        $this->printSummary();

        return self::SUCCESS;
    }
}
