<?php

namespace Modules\TitanCore\Console\Commands\Mdk;

/**
 * Scaffold a Titan agent.
 *
 * Usage:
 *   php artisan titan:make-agent ScoutAgent
 *   php artisan titan:make-agent ScoutAgent --module=Dispatch
 */
class MakeAgentCommand extends MdkBaseCommand
{
    protected $signature = 'titan:make-agent
                            {name : Agent name in PascalCase (e.g. ScoutAgent)}
                            {--module= : Module to create the agent in (defaults to name)}
                            {--dry-run : Preview files without writing}
                            {--force : Overwrite existing files}';

    protected $description = 'Scaffold a Titan agent class and manifest.';

    public function handle(): int
    {
        $name   = $this->toPascal((string) $this->argument('name'));
        $module = $this->option('module') ? $this->toPascal((string) $this->option('module')) : $name;
        $vars   = $this->buildVars($name, $module);

        $isDryRun = (bool) $this->option('dry-run');
        $this->components->info(($isDryRun ? '[DRY-RUN] ' : '')."Creating agent <fg=cyan>{$name}Agent</> in module <fg=cyan>{$module}</>");
        $this->newLine();

        $root = $this->modulesPath($module);

        $this->writeFile(
            $root."/Agents/{$name}Agent.php",
            $this->renderStub('Agent.php.stub', $vars)
        );

        $this->writeFile(
            $root.'/Agents/'.$name.'/agent.manifest.json',
            $this->renderStub('agent.json.stub', $vars)
        );

        $this->printSummary();

        if (! $isDryRun && ! empty($this->written)) {
            $this->newLine();
            $this->line("  Register <fg=cyan>{$name}Agent</> in your module's <fg=cyan>manifests/ai.manifest.json</> under the <fg=cyan>agents</> key.");
        }

        return self::SUCCESS;
    }
}
