<?php

namespace Modules\TitanCore\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

/**
 * Platform-wide health check command.
 *
 * Usage:
 *   php artisan modules:health                  # check all modules
 *   php artisan modules:health CleaningJobs     # check one module
 *   php artisan modules:health --json           # machine-readable output
 *
 * For each module the command tries (in order):
 *   1. <alias>:health artisan command (module-specific)
 *   2. A health/checks.php PHP file in the module directory
 *
 * Exit codes:
 *   0 — all checks passed
 *   1 — one or more checks failed
 */
class ModulesHealthCommand extends Command
{
    protected $signature = 'modules:health
                            {module? : Module name to check (default: all)}
                            {--json : Output results as JSON}';

    protected $description = 'Run health checks for one or all Titan modules.';

    public function handle(): int
    {
        $moduleName = $this->argument('module');
        $asJson     = (bool) $this->option('json');

        $targets = $moduleName
            ? [$moduleName]
            : $this->discoverModules();

        if (empty($targets)) {
            $this->warn('No modules found.');
            return self::SUCCESS;
        }

        $allResults = [];
        $anyFailed  = false;

        foreach ($targets as $target) {
            $result                   = $this->checkModule($target);
            $allResults[$target]      = $result;
            if (! $result['ok']) {
                $anyFailed = true;
            }
        }

        if ($asJson) {
            $this->line(json_encode($allResults, JSON_PRETTY_PRINT));
            return $anyFailed ? self::FAILURE : self::SUCCESS;
        }

        // Human-readable table
        foreach ($allResults as $module => $result) {
            $icon = $result['ok'] ? '<fg=green>✓</>' : '<fg=red>✗</>';
            $this->line("{$icon} <comment>{$module}</comment>: {$result['summary']}");

            if (! empty($result['errors'])) {
                foreach ($result['errors'] as $err) {
                    $this->line("    <fg=red>↳</> {$err}");
                }
            }
        }

        return $anyFailed ? self::FAILURE : self::SUCCESS;
    }

    // ──────────────────────────────────────────────────────────────────────────

    /**
     * @return array{ok: bool, summary: string, errors: string[]}
     */
    private function checkModule(string $moduleName): array
    {
        $alias = strtolower($moduleName);

        // 1. Try module-specific artisan health command
        try {
            $exitCode = Artisan::call("{$alias}:health", ['--json' => true]);
            $output   = trim(Artisan::output());
            $decoded  = $output ? json_decode($output, true) : null;

            if (is_array($decoded)) {
                $failed = array_filter($decoded, fn($v) => isset($v['ok']) && ! $v['ok']);
                return [
                    'ok'      => $exitCode === 0 && empty($failed),
                    'summary' => $exitCode === 0 ? 'all checks passed' : 'one or more checks failed',
                    'errors'  => array_values(array_map(fn($v) => $v['label'] ?? 'unknown', $failed)),
                ];
            }

            return [
                'ok'      => $exitCode === 0,
                'summary' => $exitCode === 0 ? 'health command passed' : 'health command failed',
                'errors'  => $exitCode !== 0 ? [trim($output)] : [],
            ];
        } catch (\Throwable) {
            // Fall through to static checks
        }

        // 2. Static checks.php
        $checksFile = base_path("Modules/{$moduleName}/health/checks.php");
        if (file_exists($checksFile)) {
            return $this->runStaticChecks($checksFile);
        }

        // 3. Minimal fallback: module.json existence
        $manifestOk = file_exists(base_path("Modules/{$moduleName}/module.json"));
        return [
            'ok'      => $manifestOk,
            'summary' => $manifestOk ? 'module.json present (no health command)' : 'module.json MISSING',
            'errors'  => $manifestOk ? [] : ['module.json not found'],
        ];
    }

    /**
     * @return array{ok: bool, summary: string, errors: string[]}
     */
    private function runStaticChecks(string $checksFile): array
    {
        $checks = require $checksFile;

        if (! is_array($checks)) {
            return ['ok' => false, 'summary' => 'checks.php did not return an array', 'errors' => []];
        }

        $failed = array_filter($checks, fn($c) => isset($c['ok']) && ! $c['ok']);
        $errors = array_values(array_map(fn($c) => ($c['label'] ?? $c['id'] ?? 'unknown') . ': ' . ($c['hint'] ?? ''), $failed));

        return [
            'ok'      => empty($failed),
            'summary' => empty($failed)
                ? count($checks) . ' checks passed'
                : count($failed) . '/' . count($checks) . ' checks failed',
            'errors'  => $errors,
        ];
    }

    /**
     * @return string[]
     */
    private function discoverModules(): array
    {
        $base    = base_path('Modules');
        $modules = [];

        foreach (glob($base . '/*/module.json') ?: [] as $manifest) {
            $modules[] = basename(dirname($manifest));
        }

        sort($modules);
        return $modules;
    }
}
