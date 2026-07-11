<?php

namespace Modules\TitanCore\Console\Commands\Mdk;

/**
 * Scaffold a complete production-ready Titan module.
 *
 * Usage:
 *   php artisan titan:make-module CRM
 *   php artisan titan:make-module CRM --dry-run
 *   php artisan titan:make-module CRM --force
 *
 * Generated structure:
 *
 *   Modules/{Name}/
 *     Config/config.php
 *     Contracts/
 *     DTO/
 *     Events/
 *     Exceptions/
 *     Http/Controllers/Api/V1/
 *     Http/Requests/
 *     Http/Resources/
 *     Providers/{Name}ServiceProvider.php
 *     Resources/lang/en/
 *     Routes/api.php
 *     Routes/web.php
 *     Services/
 *     Support/
 *     Tests/Feature/
 *     Tests/Unit/
 *     module.json
 *     composer.json
 *     README.md
 */
class MakeModuleCommand extends MdkBaseCommand
{
    protected $signature = 'titan:make-module
                            {name : Module name in PascalCase (e.g. CRM)}
                            {--dry-run : Preview files without writing}
                            {--force : Overwrite existing files}';

    protected $description = 'Scaffold a complete production-ready Titan module.';

    public function handle(): int
    {
        $name = $this->toPascal((string) $this->argument('name'));
        $vars = $this->buildVars($name);

        $isDryRun = (bool) $this->option('dry-run');

        $this->components->info(
            ($isDryRun ? '[DRY-RUN] ' : '')."Scaffolding module <fg=cyan>{$name}</>"
        );
        $this->newLine();

        $root = $this->modulesPath($name);

        // ── Root manifests and docs ────────────────────────────────────────────
        $this->writeFile($root.'/module.json',   $this->renderStub('module.json.stub',   $vars));
        $this->writeFile($root.'/composer.json', $this->renderStub('composer.json.stub', $vars));
        $this->writeFile($root.'/README.md',     $this->renderStub('README.md.stub',     $vars));

        // ── Service Provider ──────────────────────────────────────────────────
        $this->writeFile(
            $root."/Providers/{$name}ServiceProvider.php",
            $this->renderStub('ServiceProvider.php.stub', $vars)
        );

        // ── Config ────────────────────────────────────────────────────────────
        $this->writeFile($root.'/Config/config.php', $this->renderStub('config.php.stub', $vars));

        // ── Routes ────────────────────────────────────────────────────────────
        $this->writeFile($root.'/Routes/api.php', $this->renderStub('routes.api.php.stub', $vars));
        $this->writeFile($root.'/Routes/web.php', $this->renderStub('routes.web.php.stub', $vars));

        // ── Empty directories (tracked via .gitkeep) ─────────────────────────
        foreach ([
            'Contracts',
            'DTO',
            'Events',
            'Exceptions',
            'Http/Controllers/Api/V1',
            'Http/Requests',
            'Http/Resources',
            'Resources/lang/en',
            'Services',
            'Support',
            'Tests/Feature',
            'Tests/Unit',
        ] as $dir) {
            $this->writeGitkeep($root.'/'.$dir);
        }

        $this->printSummary();

        if (! $isDryRun && ! empty($this->written)) {
            $this->newLine();
            $this->components->info('Next steps:');
            $this->line("  1. Register <fg=cyan>{$name}ServiceProvider</> in your application or module loader.");
            $this->line("  2. Run <fg=cyan>php artisan titan:validate-module {$name}</> to confirm everything is wired.");
            $this->line("  3. Run <fg=cyan>php artisan modules:doctor</> to check the dependency graph.");
        }

        return self::SUCCESS;
    }
}
