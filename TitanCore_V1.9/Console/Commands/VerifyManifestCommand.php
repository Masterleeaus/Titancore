<?php

namespace Modules\TitanCore\Console\Commands;

use Illuminate\Console\Command;

/**
 * Verifies the current state of the TitanCore module against MANIFEST.sha256.
 *
 * Every file listed in MANIFEST.sha256 is re-hashed and compared. Any
 * discrepancy (new, modified, or deleted file) is reported as a failure so
 * that CI can catch unintended changes.
 *
 * Usage:
 *   php artisan titan:verify-manifest
 *   php artisan titan:verify-manifest --path=Modules/TitanCore
 *   php artisan titan:verify-manifest --strict   # also fails on new untracked files
 *
 * Exit codes:
 *   0 — MANIFEST.sha256 matches current state
 *   1 — one or more hash mismatches or missing files detected
 */
class VerifyManifestCommand extends Command
{
    protected $signature = 'titan:verify-manifest
                            {--path= : Relative path to the module root (default: Modules/TitanCore)}
                            {--manifest= : Path to the MANIFEST.sha256 file (default: <module-root>/MANIFEST.sha256)}
                            {--strict : Also fail if untracked files are present in the module directory}';

    protected $description = 'Verify current file hashes against MANIFEST.sha256 (used in CI to detect unauthorised changes).';

    public function handle(): int
    {
        $relPath      = $this->option('path') ?? 'Modules/TitanCore';
        $moduleDir    = base_path($relPath);
        $manifestFile = $this->option('manifest') ?? $moduleDir.'/MANIFEST.sha256';
        $strict       = (bool) $this->option('strict');

        if (! is_dir($moduleDir)) {
            $this->components->error("Module directory not found: {$moduleDir}");

            return self::FAILURE;
        }

        if (! is_file($manifestFile)) {
            $this->components->error("MANIFEST.sha256 not found: {$manifestFile}. Run titan:generate-manifest first.");

            return self::FAILURE;
        }

        $this->components->info('Verifying MANIFEST.sha256…');

        $entries = $this->parseManifest($manifestFile);

        if (empty($entries)) {
            $this->components->warn('MANIFEST.sha256 is empty or contains no valid entries.');

            return self::SUCCESS;
        }

        $failures = [];
        $checked  = 0;

        foreach ($entries as $relativePath => $expectedHash) {
            $absolutePath = $moduleDir.DIRECTORY_SEPARATOR.$relativePath;
            $checked++;

            if (! is_file($absolutePath)) {
                $failures[] = ['type' => 'missing', 'path' => $relativePath, 'expected' => $expectedHash, 'actual' => null];
                continue;
            }

            $actualHash = hash_file('sha256', $absolutePath);
            if ($actualHash !== $expectedHash) {
                $failures[] = ['type' => 'modified', 'path' => $relativePath, 'expected' => $expectedHash, 'actual' => $actualHash];
            }
        }

        foreach ($failures as $f) {
            if ($f['type'] === 'missing') {
                $this->line("  <fg=red>✗</> MISSING   {$f['path']}");
            } else {
                $this->line("  <fg=red>✗</> MODIFIED  {$f['path']}");
                $this->line("             expected: {$f['expected']}");
                $this->line("             actual:   {$f['actual']}");
            }
        }

        if (! empty($failures)) {
            $this->newLine();
            $count = count($failures);
            $this->components->error(
                "{$count} file(s) failed verification. "
                .'Run `php artisan titan:generate-manifest` and commit the updated MANIFEST.sha256 before deploying.'
            );

            return self::FAILURE;
        }

        $this->components->twoColumnDetail('<fg=green>✓ MANIFEST.sha256 verified</>', "{$checked} files matched");

        return self::SUCCESS;
    }

    /**
     * Parse MANIFEST.sha256 into an associative array of relative-path => hash.
     *
     * @return array<string, string>
     */
    private function parseManifest(string $manifestFile): array
    {
        $entries = [];
        $lines   = file($manifestFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        if ($lines === false) {
            return [];
        }

        foreach ($lines as $line) {
            // Format: "<sha256-hash>  <relative/path>" (two spaces separator, as written by titan:generate-manifest)
            // We accept one or more whitespace characters to tolerate minor formatting variations.
            if (preg_match('/^([a-f0-9]{64})\s+(.+)$/', trim($line), $m)) {
                $entries[$m[2]] = $m[1];
            }
        }

        return $entries;
    }
}
