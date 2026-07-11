<?php

namespace Modules\TitanCore\Console\Commands\Mdk;

/**
 * Scaffold a Titan tool.
 *
 * Usage:
 *   php artisan titan:make-tool QuoteGenerator
 *   php artisan titan:make-tool QuoteGenerator --module=CRM
 */
class MakeToolCommand extends MdkBaseCommand
{
    protected $signature = 'titan:make-tool
                            {name : Tool name in PascalCase (e.g. QuoteGenerator)}
                            {--module= : Module to create the tool in (defaults to name)}
                            {--dry-run : Preview files without writing}
                            {--force : Overwrite existing files}';

    protected $description = 'Scaffold a Titan tool class and manifest.';

    public function handle(): int
    {
        $name   = $this->toPascal((string) $this->argument('name'));
        $module = $this->option('module') ? $this->toPascal((string) $this->option('module')) : $name;
        $vars   = $this->buildVars($name, $module);

        $isDryRun = (bool) $this->option('dry-run');
        $this->components->info(($isDryRun ? '[DRY-RUN] ' : '')."Creating tool <fg=cyan>{$name}Tool</> in module <fg=cyan>{$module}</>");
        $this->newLine();

        $root = $this->modulesPath($module);

        $this->writeFile(
            $root."/Tools/{$name}Tool.php",
            $this->renderStub('Tool.php.stub', $vars)
        );

        $this->writeFile(
            $root.'/manifests/tools/'.$vars['{{ name }}'].'.json',
            $this->renderStub('tool.json.stub', $vars)
        );

        $this->printSummary();

        if (! $isDryRun && ! empty($this->written)) {
            $this->newLine();
            $this->line("  Register <fg=cyan>{$name}Tool</> in your module's <fg=cyan>manifests/ai.manifest.json</> under the <fg=cyan>tools</> key.");
        }

        return self::SUCCESS;
    }
}
