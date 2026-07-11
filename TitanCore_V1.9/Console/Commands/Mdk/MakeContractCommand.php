<?php

namespace Modules\TitanCore\Console\Commands\Mdk;

/**
 * Scaffold a Titan contract interface.
 *
 * Usage:
 *   php artisan titan:make-contract InvoiceContract
 *   php artisan titan:make-contract InvoiceContract --module=Billing
 */
class MakeContractCommand extends MdkBaseCommand
{
    protected $signature = 'titan:make-contract
                            {name : Contract name in PascalCase (e.g. InvoiceContract)}
                            {--module= : Module to create the contract in (defaults to name)}
                            {--dry-run : Preview files without writing}
                            {--force : Overwrite existing files}';

    protected $description = 'Scaffold a Titan contract interface.';

    public function handle(): int
    {
        $name   = $this->toPascal((string) $this->argument('name'));
        $module = $this->option('module') ? $this->toPascal((string) $this->option('module')) : $name;
        $vars   = $this->buildVars($name, $module);

        $isDryRun = (bool) $this->option('dry-run');
        $this->components->info(($isDryRun ? '[DRY-RUN] ' : '')."Creating contract <fg=cyan>{$name}</> in module <fg=cyan>{$module}</>");
        $this->newLine();

        $root = $this->modulesPath($module);

        $this->writeFile(
            $root."/Contracts/{$name}.php",
            $this->renderStub('Contract.php.stub', $vars)
        );

        $this->printSummary();

        return self::SUCCESS;
    }
}
