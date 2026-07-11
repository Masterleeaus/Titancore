<?php

namespace Modules\TitanCore\Console\Commands\Mdk;

/**
 * Scaffold a Titan prompt class and manifest.
 *
 * Usage:
 *   php artisan titan:make-prompt CleaningQuote
 *   php artisan titan:make-prompt CleaningQuote --module=Cleaning
 */
class MakePromptCommand extends MdkBaseCommand
{
    protected $signature = 'titan:make-prompt
                            {name : Prompt name in PascalCase (e.g. CleaningQuote)}
                            {--module= : Module to create the prompt in (defaults to name)}
                            {--dry-run : Preview files without writing}
                            {--force : Overwrite existing files}';

    protected $description = 'Scaffold a Titan prompt class and manifest.';

    public function handle(): int
    {
        $name   = $this->toPascal((string) $this->argument('name'));
        $module = $this->option('module') ? $this->toPascal((string) $this->option('module')) : $name;
        $vars   = $this->buildVars($name, $module);

        $isDryRun = (bool) $this->option('dry-run');
        $this->components->info(($isDryRun ? '[DRY-RUN] ' : '')."Creating prompt <fg=cyan>{$name}Prompt</> in module <fg=cyan>{$module}</>");
        $this->newLine();

        $root = $this->modulesPath($module);

        $this->writeFile(
            $root."/Prompts/{$name}Prompt.php",
            $this->renderStub('Prompt.php.stub', $vars)
        );

        $this->writeFile(
            $root.'/manifests/prompts/'.$vars['{{ name }}'].'.json',
            $this->renderStub('prompt.json.stub', $vars)
        );

        $this->printSummary();

        return self::SUCCESS;
    }
}
