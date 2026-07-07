<?php

namespace Modules\TitanCore\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Modules\TitanCore\AI\ManifestValidationIssue;
use Modules\TitanCore\AI\ManifestValidator;

/**
 * Artisan command to validate all module ai_tools.json manifests.
 *
 * Usage:
 *   php artisan titan:validate-manifests
 *   php artisan titan:validate-manifests --module=CRMCore
 *
 * Exit codes:
 *   0 — all manifests valid (errors and warnings are absent)
 *   1 — one or more errors found
 */
class ValidateManifestsCommand extends Command
{
    protected $signature = 'titan:validate-manifests
                            {--module= : Validate only this module (e.g. --module=CRMCore)}
                            {--warnings-as-errors : Treat warnings as errors for CI strictness}';

    protected $description = 'Validate all module ai_tools.json manifests (class existence, required fields, execute() return type).';

    public function handle(): int
    {
        $moduleName = $this->option('module');
        $strict     = (bool) $this->option('warnings-as-errors');

        $validator = new ManifestValidator();

        $this->components->info($moduleName
            ? "Validating AI tools manifest for module: {$moduleName}"
            : 'Validating AI tools manifests for all modules'
        );

        $issues = $moduleName
            ? $validator->validateModule($moduleName)
            : $validator->validateAll();

        if (empty($issues)) {
            $this->components->twoColumnDetail('<fg=green>✓ All AI tool manifests are valid</>', '');

            return self::SUCCESS;
        }

        $errors   = array_filter($issues, fn (ManifestValidationIssue $i) => $i->isError());
        $warnings = array_filter($issues, fn (ManifestValidationIssue $i) => $i->isWarning());

        foreach ($errors as $issue) {
            $this->line(
                "  <fg=red>✗</> <fg=cyan>{$issue->module()}</fg=cyan> / <fg=yellow>{$issue->tool()}</fg=yellow>: {$issue->message()}"
            );
        }

        foreach ($warnings as $issue) {
            $this->line(
                "  <fg=yellow>⚠</> <fg=cyan>{$issue->module()}</fg=cyan> / <fg=yellow>{$issue->tool()}</fg=yellow>: {$issue->message()}"
            );
        }

        $errorCount   = count($errors);
        $warningCount = count($warnings);

        $this->newLine();
        $this->line("  Errors: <fg=red>{$errorCount}</>, Warnings: <fg=yellow>{$warningCount}</>");

        $hasFatalIssues = $errorCount > 0 || ($strict && $warningCount > 0);

        if ($hasFatalIssues) {
            Log::critical('TitanCore.ValidateManifests: AI tool manifest validation failed', [
                'errors'   => $errorCount,
                'warnings' => $warningCount,
                'module'   => $moduleName ?? 'all',
            ]);

            $this->dispatchHealthCheckFailed($errorCount, $warningCount);

            $this->components->error('Manifest validation failed. Fix the issues listed above.');

            return self::FAILURE;
        }

        $this->components->warn('Manifest validation passed with warnings.');

        return self::SUCCESS;
    }

    private function dispatchHealthCheckFailed(int $errors, int $warnings): void
    {
        try {
            Event::dispatch('TitanCore.HealthCheckFailed', [
                'check'    => 'ai_manifest_validation',
                'errors'   => $errors,
                'warnings' => $warnings,
                'ts'       => now()->toIso8601String(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('TitanCore.ValidateManifests: could not dispatch HealthCheckFailed event', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
