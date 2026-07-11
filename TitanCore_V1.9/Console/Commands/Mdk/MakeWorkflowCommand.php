<?php

namespace Modules\TitanCore\Console\Commands\Mdk;

/**
 * Scaffold a Titan workflow.
 *
 * Usage:
 *   php artisan titan:make-workflow CustomerOnboarding
 *   php artisan titan:make-workflow CustomerOnboarding --module=CRM
 */
class MakeWorkflowCommand extends MdkBaseCommand
{
    protected $signature = 'titan:make-workflow
                            {name : Workflow name in PascalCase (e.g. CustomerOnboarding)}
                            {--module= : Module to create the workflow in (defaults to name)}
                            {--dry-run : Preview files without writing}
                            {--force : Overwrite existing files}';

    protected $description = 'Scaffold a Titan workflow class and manifest.';

    public function handle(): int
    {
        $name   = $this->toPascal((string) $this->argument('name'));
        $module = $this->option('module') ? $this->toPascal((string) $this->option('module')) : $name;
        $vars   = $this->buildVars($name, $module);

        $isDryRun = (bool) $this->option('dry-run');
        $this->components->info(($isDryRun ? '[DRY-RUN] ' : '')."Creating workflow <fg=cyan>{$name}Workflow</> in module <fg=cyan>{$module}</>");
        $this->newLine();

        $root = $this->modulesPath($module);

        $this->writeFile(
            $root."/Workflows/{$name}Workflow.php",
            $this->renderStub('Workflow.php.stub', $vars)
        );

        $this->writeFile(
            $root.'/manifests/workflows/'.$vars['{{ name }}'].'.json',
            $this->renderStub('workflow.json.stub', $vars)
        );

        $this->printSummary();

        if (! $isDryRun && ! empty($this->written)) {
            $this->newLine();
            $this->line("  Register <fg=cyan>{$name}Workflow</> in your module's <fg=cyan>manifests/workflow.manifest.json</> under the <fg=cyan>workflows</> key.");
        }

        return self::SUCCESS;
    }
}
