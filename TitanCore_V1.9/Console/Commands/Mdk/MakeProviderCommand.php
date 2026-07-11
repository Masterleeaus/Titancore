<?php

namespace Modules\TitanCore\Console\Commands\Mdk;

/**
 * Scaffold a Titan AI provider.
 *
 * Usage:
 *   php artisan titan:make-provider Anthropic
 *   php artisan titan:make-provider Anthropic --module=AI
 */
class MakeProviderCommand extends MdkBaseCommand
{
    protected $signature = 'titan:make-provider
                            {name : Provider name in PascalCase (e.g. Anthropic)}
                            {--module= : Module to create the provider in (defaults to name)}
                            {--dry-run : Preview files without writing}
                            {--force : Overwrite existing files}';

    protected $description = 'Scaffold a Titan AI provider class and manifest.';

    public function handle(): int
    {
        $name   = $this->toPascal((string) $this->argument('name'));
        $module = $this->option('module') ? $this->toPascal((string) $this->option('module')) : $name;
        $vars   = $this->buildVars($name, $module);

        $isDryRun = (bool) $this->option('dry-run');
        $this->components->info(($isDryRun ? '[DRY-RUN] ' : '')."Creating provider <fg=cyan>{$name}</> in module <fg=cyan>{$module}</>");
        $this->newLine();

        $root = $this->modulesPath($module);

        $this->writeFile(
            $root."/Providers/{$name}Provider.php",
            $this->renderStub('AiProvider.php.stub', $vars)
        );

        $this->writeFile(
            $root.'/manifests/provider.json',
            $this->renderStub('provider.json.stub', $vars)
        );

        $this->printSummary();

        if (! $isDryRun && ! empty($this->written)) {
            $this->newLine();
            $this->line("  Register <fg=cyan>{$name}Provider</> in your module's service provider and bind it to the TitanCore provider contract.");
        }

        return self::SUCCESS;
    }
}
