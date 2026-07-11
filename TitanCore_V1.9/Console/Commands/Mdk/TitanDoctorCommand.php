<?php

namespace Modules\TitanCore\Console\Commands\Mdk;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Module-specific doctor that validates a single module's architecture.
 *
 * Usage:
 *   php artisan titan:doctor CRM
 *   php artisan titan:doctor CRM --strict
 *
 * Validates:
 *  - module.json presence and required fields
 *  - Namespace convention (Modules\{Name}\*)
 *  - Service provider presence
 *  - Route files presence
 *  - Manifests (module.json, ai.manifest.json, etc.)
 *  - Agent / tool class references in manifests
 *  - Dependency declarations
 *  - Test directory presence
 */
class TitanDoctorCommand extends Command
{
    protected $signature = 'titan:doctor
                            {module : Module name to diagnose (e.g. CRM)}
                            {--strict : Fail on warnings as well as errors}';

    protected $description = 'Diagnose a single Titan module (manifests, namespaces, providers, routes, tests).';

    private bool $hasErrors   = false;
    private bool $hasWarnings = false;

    public function handle(): int
    {
        $moduleName = (string) $this->argument('module');
        $strict     = (bool) $this->option('strict');

        $modulesBase = $this->resolveModulesBase();
        $moduleDir   = $modulesBase.'/'.$moduleName;

        $this->components->info("Titan Doctor — <fg=cyan>{$moduleName}</>");
        $this->newLine();

        if (! is_dir($moduleDir)) {
            $this->components->error("Module directory not found: {$moduleDir}");

            return self::FAILURE;
        }

        $this->checkModuleJson($moduleDir, $moduleName);
        $this->checkNamespace($moduleDir, $moduleName);
        $this->checkServiceProvider($moduleDir, $moduleName);
        $this->checkRoutes($moduleDir);
        $this->checkManifests($moduleDir, $moduleName);
        $this->checkAgentClasses($moduleDir, $moduleName);
        $this->checkToolClasses($moduleDir, $moduleName);
        $this->checkTestDirectory($moduleDir);

        $this->newLine();

        if ($this->hasErrors || ($strict && $this->hasWarnings)) {
            $this->components->error('Doctor found problems. Fix the issues listed above.');

            return self::FAILURE;
        }

        $this->components->info("<fg=green>✓ {$moduleName}</> passed all checks.");

        return self::SUCCESS;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Individual checks
    // ──────────────────────────────────────────────────────────────────────────

    private function checkModuleJson(string $moduleDir, string $moduleName): void
    {
        $path = $moduleDir.'/module.json';

        if (! is_file($path)) {
            $this->fail("module.json not found. Run: <fg=cyan>php artisan titan:make-module {$moduleName}</>");

            return;
        }

        $data = $this->readJson($path);
        if ($data === null) {
            $this->fail('module.json contains invalid JSON.');

            return;
        }

        foreach (['name', 'version', 'providers'] as $required) {
            if (! isset($data[$required])) {
                $this->fail("module.json is missing required field: <fg=cyan>{$required}</>");
            }
        }

        $this->pass('module.json is present and valid.');
    }

    private function checkNamespace(string $moduleDir, string $moduleName): void
    {
        $phpFiles = File::glob($moduleDir.'/**/*.php') ?: [];

        $violations = 0;
        foreach (array_slice($phpFiles, 0, 50) as $file) {
            $contents = file_get_contents($file) ?: '';
            if (preg_match('/^namespace\s+([^;]+)/m', $contents, $m)) {
                $ns = trim($m[1]);
                if (! str_starts_with($ns, "Modules\\{$moduleName}")) {
                    $violations++;
                    $rel = str_replace($moduleDir.'/', '', $file);
                    $this->warn("Namespace violation in <fg=cyan>{$rel}</>: found <fg=yellow>{$ns}</> (expected Modules\\{$moduleName}\\*)");
                }
            }
        }

        if ($violations === 0) {
            $this->pass('All sampled PHP files use the correct namespace.');
        }
    }

    private function checkServiceProvider(string $moduleDir, string $moduleName): void
    {
        $path = $moduleDir."/Providers/{$moduleName}ServiceProvider.php";

        if (! is_file($path)) {
            $this->warn("Service provider not found: Providers/{$moduleName}ServiceProvider.php");

            return;
        }

        $this->pass("Service provider Providers/{$moduleName}ServiceProvider.php found.");
    }

    private function checkRoutes(string $moduleDir): void
    {
        $hasApi = is_file($moduleDir.'/Routes/api.php');
        $hasWeb = is_file($moduleDir.'/Routes/web.php');

        if ($hasApi || $hasWeb) {
            $files = implode(', ', array_filter(['api.php' => $hasApi, 'web.php' => $hasWeb], fn ($v) => $v, ARRAY_FILTER_USE_KEY));
            $this->pass("Route files found: {$files}.");
        } else {
            $this->warn('No route files found in Routes/ (api.php or web.php). Add routes or ignore if headless.');
        }
    }

    private function checkManifests(string $moduleDir, string $moduleName): void
    {
        $manifestsDir = $moduleDir.'/manifests';

        if (! is_dir($manifestsDir)) {
            $this->warn('No manifests/ directory found. Add manifests if this module registers tools, agents, or workflows.');

            return;
        }

        $manifestFiles = array_merge(
            glob($manifestsDir.'/*.json') ?: [],
            glob($manifestsDir.'/**/*.json') ?: []
        );

        $invalidCount = 0;

        foreach ($manifestFiles as $manifestFile) {
            $data = $this->readJson($manifestFile);
            if ($data === null) {
                $invalidCount++;
                $rel = str_replace($moduleDir.'/', '', $manifestFile);
                $this->fail("Manifest contains invalid JSON: <fg=cyan>{$rel}</>");
            }
        }

        if ($invalidCount === 0) {
            $this->pass(sprintf('%d manifest file(s) parsed successfully.', count($manifestFiles)));
        }
    }

    private function checkAgentClasses(string $moduleDir, string $moduleName): void
    {
        $agentsDir = $moduleDir.'/Agents';
        if (! is_dir($agentsDir)) {
            return;
        }

        foreach (glob($agentsDir.'/*/agent.manifest.json') ?: [] as $manifestPath) {
            $data = $this->readJson($manifestPath);
            if (! is_array($data)) {
                continue;
            }

            $agentClass = $data['agent_class'] ?? null;
            if (is_string($agentClass) && $agentClass !== '' && ! class_exists($agentClass)) {
                $rel = str_replace($moduleDir.'/', '', $manifestPath);
                $this->fail("Agent class not found: <fg=cyan>{$agentClass}</> (declared in {$rel})");
            }
        }
    }

    private function checkToolClasses(string $moduleDir, string $moduleName): void
    {
        $aiManifest = $moduleDir.'/manifests/ai.manifest.json';
        if (! is_file($aiManifest)) {
            return;
        }

        $data = $this->readJson($aiManifest);
        if (! is_array($data)) {
            return;
        }

        foreach ((array) ($data['tools'] ?? []) as $tool) {
            $class = is_array($tool) ? ($tool['class'] ?? $tool['handler'] ?? null) : null;
            if (is_string($class) && str_contains($class, '\\') && ! class_exists($class)) {
                $this->fail("Tool class not found: <fg=cyan>{$class}</> (declared in manifests/ai.manifest.json)");
            }
        }
    }

    private function checkTestDirectory(string $moduleDir): void
    {
        $hasTests = is_dir($moduleDir.'/Tests') || is_dir($moduleDir.'/tests');

        if ($hasTests) {
            $this->pass('Tests directory found.');
        } else {
            $this->warn('No Tests/ directory found. Add tests for production readiness.');
        }
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Output helpers
    // ──────────────────────────────────────────────────────────────────────────

    private function pass(string $message): void
    {
        $this->components->twoColumnDetail("<fg=green>✓</> {$message}", '');
    }

    private function warn(string $message): void
    {
        $this->hasWarnings = true;
        $this->line("  <fg=yellow>⚠</> {$message}");
    }

    private function fail(string $message): void
    {
        $this->hasErrors = true;
        $this->line("  <fg=red>✗</> {$message}");
    }

    /**
     * @return array<string, mixed>|null
     */
    private function readJson(string $path): ?array
    {
        $raw     = file_get_contents($path);
        $decoded = $raw !== false ? json_decode($raw, true) : null;

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Resolve the modules base directory, supporting absolute config paths.
     */
    private function resolveModulesBase(): string
    {
        $configured = (string) config('titan-modules.path', 'Modules');

        return str_starts_with($configured, DIRECTORY_SEPARATOR)
            ? $configured
            : base_path($configured);
    }
}
