<?php

namespace Modules\TitanCore\Console\Commands\Mdk;

/**
 * Scaffold a Titan policy class.
 *
 * Usage:
 *   php artisan titan:make-policy QuotePolicy
 *   php artisan titan:make-policy QuotePolicy --module=Cleaning
 */
class MakePolicyCommand extends MdkBaseCommand
{
    protected $signature = 'titan:make-policy
                            {name : Policy name in PascalCase (e.g. QuotePolicy)}
                            {--module= : Module to create the policy in (defaults to name)}
                            {--dry-run : Preview files without writing}
                            {--force : Overwrite existing files}';

    protected $description = 'Scaffold a Titan policy class.';

    public function handle(): int
    {
        $name   = $this->toPascal((string) $this->argument('name'));
        $module = $this->option('module') ? $this->toPascal((string) $this->option('module')) : $name;
        $vars   = $this->buildVars($name, $module);

        $isDryRun = (bool) $this->option('dry-run');
        $this->components->info(($isDryRun ? '[DRY-RUN] ' : '')."Creating policy <fg=cyan>{$name}Policy</> in module <fg=cyan>{$module}</>");
        $this->newLine();

        $root = $this->modulesPath($module);

        $this->writeFile(
            $root."/Policies/{$name}Policy.php",
            $this->renderStub('Policy.php.stub', $vars)
        );

        $this->printSummary();

        return self::SUCCESS;
    }
}
