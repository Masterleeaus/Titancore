<?php

namespace Modules\TitanCore\Console\Commands\Mdk;

/**
 * Scaffold a Titan plugin.
 *
 * Usage:
 *   php artisan titan:make-plugin Telegram
 *   php artisan titan:make-plugin Telegram --module=Notifications
 */
class MakePluginCommand extends MdkBaseCommand
{
    protected $signature = 'titan:make-plugin
                            {name : Plugin name in PascalCase (e.g. Telegram)}
                            {--module= : Module to create the plugin in (defaults to name)}
                            {--dry-run : Preview files without writing}
                            {--force : Overwrite existing files}';

    protected $description = 'Scaffold a Titan plugin class.';

    public function handle(): int
    {
        $name   = $this->toPascal((string) $this->argument('name'));
        $module = $this->option('module') ? $this->toPascal((string) $this->option('module')) : $name;
        $vars   = $this->buildVars($name, $module);

        $isDryRun = (bool) $this->option('dry-run');
        $this->components->info(($isDryRun ? '[DRY-RUN] ' : '')."Creating plugin <fg=cyan>{$name}</> in module <fg=cyan>{$module}</>");
        $this->newLine();

        $root = $this->modulesPath($module);

        $this->writeFile(
            $root."/Plugins/{$name}Plugin.php",
            $this->renderStub('Plugin.php.stub', $vars)
        );

        $this->printSummary();

        return self::SUCCESS;
    }
}
