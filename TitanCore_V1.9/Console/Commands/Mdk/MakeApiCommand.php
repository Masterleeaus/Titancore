<?php

namespace Modules\TitanCore\Console\Commands\Mdk;

/**
 * Scaffold a complete Titan API layer (controller, request, resource, routes).
 *
 * Usage:
 *   php artisan titan:make-api Leads
 *   php artisan titan:make-api Leads --module=CRM
 */
class MakeApiCommand extends MdkBaseCommand
{
    protected $signature = 'titan:make-api
                            {name : Resource name in PascalCase (e.g. Leads)}
                            {--module= : Module to create the API in (defaults to name)}
                            {--dry-run : Preview files without writing}
                            {--force : Overwrite existing files}';

    protected $description = 'Scaffold a Titan API controller, request, resource, and routes.';

    public function handle(): int
    {
        $name   = $this->toPascal((string) $this->argument('name'));
        $module = $this->option('module') ? $this->toPascal((string) $this->option('module')) : $name;
        $vars   = $this->buildVars($name, $module);

        $isDryRun = (bool) $this->option('dry-run');
        $this->components->info(($isDryRun ? '[DRY-RUN] ' : '')."Creating API <fg=cyan>{$name}</> in module <fg=cyan>{$module}</>");
        $this->newLine();

        $root = $this->modulesPath($module);

        $this->writeFile(
            $root."/Http/Controllers/Api/V1/{$name}Controller.php",
            $this->renderStub('Controller.php.stub', $vars)
        );

        $this->writeFile(
            $root."/Http/Requests/{$name}Request.php",
            $this->renderStub('Request.php.stub', $vars)
        );

        $this->writeFile(
            $root."/Http/Resources/{$name}Resource.php",
            $this->renderStub('Resource.php.stub', $vars)
        );

        $this->printSummary();

        if (! $isDryRun && ! empty($this->written)) {
            $this->newLine();
            $this->line("  Add routes for <fg=cyan>{$name}Controller</> in <fg=cyan>Routes/api.php</>.");
        }

        return self::SUCCESS;
    }
}
