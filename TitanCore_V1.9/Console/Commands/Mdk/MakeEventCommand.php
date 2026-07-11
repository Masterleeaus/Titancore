<?php

namespace Modules\TitanCore\Console\Commands\Mdk;

/**
 * Scaffold a Titan event class.
 *
 * Usage:
 *   php artisan titan:make-event LeadCreated
 *   php artisan titan:make-event LeadCreated --module=CRM
 */
class MakeEventCommand extends MdkBaseCommand
{
    protected $signature = 'titan:make-event
                            {name : Event name in PascalCase (e.g. LeadCreated)}
                            {--module= : Module to create the event in (defaults to name)}
                            {--dry-run : Preview files without writing}
                            {--force : Overwrite existing files}';

    protected $description = 'Scaffold a Titan event class.';

    public function handle(): int
    {
        $name   = $this->toPascal((string) $this->argument('name'));
        $module = $this->option('module') ? $this->toPascal((string) $this->option('module')) : $name;
        $vars   = $this->buildVars($name, $module);

        $isDryRun = (bool) $this->option('dry-run');
        $this->components->info(($isDryRun ? '[DRY-RUN] ' : '')."Creating event <fg=cyan>{$name}</> in module <fg=cyan>{$module}</>");
        $this->newLine();

        $root = $this->modulesPath($module);

        $this->writeFile(
            $root."/Events/{$name}.php",
            $this->renderStub('Event.php.stub', $vars)
        );

        $this->printSummary();

        return self::SUCCESS;
    }
}
