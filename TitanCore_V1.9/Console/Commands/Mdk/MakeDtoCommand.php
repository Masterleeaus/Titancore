<?php

namespace Modules\TitanCore\Console\Commands\Mdk;

/**
 * Scaffold a Titan DTO (Data Transfer Object).
 *
 * Usage:
 *   php artisan titan:make-dto QuoteRequest
 *   php artisan titan:make-dto QuoteRequest --module=Cleaning
 */
class MakeDtoCommand extends MdkBaseCommand
{
    protected $signature = 'titan:make-dto
                            {name : DTO name in PascalCase (e.g. QuoteRequest)}
                            {--module= : Module to create the DTO in (defaults to name)}
                            {--dry-run : Preview files without writing}
                            {--force : Overwrite existing files}';

    protected $description = 'Scaffold a Titan DTO (Data Transfer Object).';

    public function handle(): int
    {
        $name   = $this->toPascal((string) $this->argument('name'));
        $module = $this->option('module') ? $this->toPascal((string) $this->option('module')) : $name;
        $vars   = $this->buildVars($name, $module);

        $isDryRun = (bool) $this->option('dry-run');
        $this->components->info(($isDryRun ? '[DRY-RUN] ' : '')."Creating DTO <fg=cyan>{$name}</> in module <fg=cyan>{$module}</>");
        $this->newLine();

        $root = $this->modulesPath($module);

        $this->writeFile(
            $root."/DTO/{$name}.php",
            $this->renderStub('Dto.php.stub', $vars)
        );

        $this->printSummary();

        return self::SUCCESS;
    }
}
