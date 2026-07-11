<?php

namespace Modules\TitanCore\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Regenerates MANIFEST.sha256 for the TitanCore module.
 *
 * The manifest records SHA-256 hashes of every tracked file relative to the
 * TitanCore module root, enabling `titan:verify-manifest` to detect accidental
 * or unauthorised file changes in CI.
 *
 * Usage:
 *   php artisan titan:generate-manifest
 *   php artisan titan:generate-manifest --path=Modules/TitanCore
 *
 * Run this command before every production deploy and commit the updated
 * MANIFEST.sha256 so that the CI verification step stays green.
 */
class GenerateManifestCommand extends Command
{
    protected $signature = 'titan:generate-manifest
                            {--path= : Relative path to the module root (default: Modules/TitanCore)}
                            {--output= : Output file path (default: <module-root>/MANIFEST.sha256)}
                            {--dry-run : Print the manifest to stdout instead of writing it}';

    protected $description = 'Regenerate MANIFEST.sha256 for the TitanCore module (run before deploy and commit the result).';

    /** Extensions / file-names to skip entirely */
    private const SKIP_EXTENSIONS = ['pyc', 'pyo', 'cache', 'log'];

    /** Individual files to exclude from hashing */
    private const SKIP_FILES = ['MANIFEST.sha256'];

    /** Directories to exclude from the walk */
    private const SKIP_DIRS = ['vendor', 'node_modules', '.git', 'storage'];

    public function handle(): int
    {
        $relPath   = $this->option('path') ?? 'Modules/TitanCore';
        $moduleDir = base_path($relPath);

        if (! is_dir($moduleDir)) {
            $this->components->error("Module directory not found: {$moduleDir}");

            return self::FAILURE;
        }

        $outputFile = $this->option('output') ?? $moduleDir.'/MANIFEST.sha256';
        $dryRun     = (bool) $this->option('dry-run');

        $this->components->info('Generating MANIFEST.sha256…');

        $files = $this->collectFiles($moduleDir);
        sort($files);

        $lines = [];
        foreach ($files as $absolutePath) {
            $hash = hash_file('sha256', $absolutePath);
            if ($hash === false) {
                $this->line("  <fg=yellow>⚠</> Could not hash: {$absolutePath}");
                continue;
            }

            $relativePath = ltrim(str_replace($moduleDir, '', $absolutePath), DIRECTORY_SEPARATOR.'/');
            $lines[]      = "{$hash}  {$relativePath}";
        }

        $content = implode("\n", $lines)."\n";

        if ($dryRun) {
            $this->line($content);
            $this->components->twoColumnDetail('<fg=green>✓ Dry run complete</>', count($lines).' files');

            return self::SUCCESS;
        }

        if (file_put_contents($outputFile, $content) === false) {
            $this->components->error("Could not write manifest to: {$outputFile}");

            return self::FAILURE;
        }

        $this->components->twoColumnDetail('<fg=green>✓ MANIFEST.sha256 written</>', count($lines).' files');
        $this->line("  <fg=cyan>Output:</> {$outputFile}");

        return self::SUCCESS;
    }

    /**
     * Recursively collect all trackable file paths under $dir.
     *
     * @return string[]
     */
    private function collectFiles(string $dir): array
    {
        $files = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }

            // Skip excluded directories
            $relPath     = ltrim(str_replace($dir, '', $file->getPathname()), DIRECTORY_SEPARATOR.'/');
            $relSegments = explode(DIRECTORY_SEPARATOR, $relPath);
            foreach (self::SKIP_DIRS as $skipDir) {
                if (in_array($skipDir, $relSegments, true)) {
                    continue 2;
                }
            }

            // Skip excluded extensions
            $ext = strtolower($file->getExtension());
            if (in_array($ext, self::SKIP_EXTENSIONS, true)) {
                continue;
            }

            if (in_array($relPath, self::SKIP_FILES, true)) {
                continue;
            }

            $files[] = $file->getPathname();
        }

        return $files;
    }
}
