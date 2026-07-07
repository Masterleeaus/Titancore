<?php

namespace Modules\TitanCore\Services\Upgrade;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

/**
 * Snapshots affected DB tables and module files before an upgrade begins.
 *
 * Snapshots are stored under storage/app/upgrade-backups/<module>/<timestamp>/
 *   tables/  — JSON-encoded row arrays per table
 *   files/   — verbatim copies of every PHP/JSON file under the module path
 */
class PreUpgradeBackupJob
{
    private string $backupRoot;

    public function __construct(?string $backupRoot = null)
    {
        $this->backupRoot = $backupRoot ?? storage_path('app/upgrade-backups');
    }

    /**
     * Perform the backup.
     *
     * @param  string  $moduleName  e.g. "CleaningJobs"
     * @param  string  $modulePath  Absolute path to module directory
     * @param  string[] $tables     Tables to snapshot (empty → auto-detect from module migrations)
     * @return string  $snapshotDir Absolute path to the created snapshot directory
     */
    public function run(string $moduleName, string $modulePath, array $tables = []): string
    {
        $timestamp   = now()->format('Y_m_d_His');
        $snapshotDir = $this->backupRoot . DIRECTORY_SEPARATOR
            . $moduleName . DIRECTORY_SEPARATOR
            . $timestamp;

        File::ensureDirectoryExists($snapshotDir . '/tables');
        File::ensureDirectoryExists($snapshotDir . '/files');

        // 1. Snapshot DB tables
        $tables = $tables ?: $this->detectTables($modulePath);
        foreach ($tables as $table) {
            $this->backupTable($table, $snapshotDir . '/tables');
        }

        // 2. Snapshot module source files
        $this->backupFiles($modulePath, $snapshotDir . '/files');

        // 3. Write metadata
        file_put_contents(
            $snapshotDir . '/snapshot.json',
            json_encode([
                'module'      => $moduleName,
                'timestamp'   => now()->toIso8601String(),
                'tables'      => $tables,
                'module_path' => $modulePath,
            ], JSON_PRETTY_PRINT)
        );

        return $snapshotDir;
    }

    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Persist every row of $table as a JSON file.
     */
    private function backupTable(string $table, string $targetDir): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        $rows = DB::table($table)->get()->toArray();
        file_put_contents(
            $targetDir . DIRECTORY_SEPARATOR . $table . '.json',
            json_encode($rows, JSON_PRETTY_PRINT)
        );
    }

    /**
     * Copy all PHP and JSON source files from $modulePath into $targetDir,
     * preserving subdirectory structure.
     */
    private function backupFiles(string $modulePath, string $targetDir): void
    {
        if (! is_dir($modulePath)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($modulePath, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            /** @var \SplFileInfo $item */
            if ($item->isDir()) {
                continue;
            }

            $ext = strtolower($item->getExtension());
            if (! in_array($ext, ['php', 'json'], true)) {
                continue;
            }

            $relative  = ltrim(substr($item->getRealPath(), strlen($modulePath)), DIRECTORY_SEPARATOR);
            $destPath  = $targetDir . DIRECTORY_SEPARATOR . $relative;
            $destParent = dirname($destPath);

            if (! is_dir($destParent)) {
                mkdir($destParent, 0755, true);
            }

            copy($item->getRealPath(), $destPath);
        }
    }

    /**
     * Attempt to infer tables touched by the module by scanning its migration files
     * for Schema::create / Schema::table calls.
     *
     * @return string[]
     */
    private function detectTables(string $modulePath): array
    {
        $tables     = [];
        $migrations = [];

        foreach (['Database/Migrations', 'Upgrade/Migrations'] as $rel) {
            $dir = $modulePath . DIRECTORY_SEPARATOR . $rel;
            if (is_dir($dir)) {
                $found = glob($dir . DIRECTORY_SEPARATOR . '*.php') ?: [];
                $migrations = array_merge($migrations, $found);
            }
        }

        foreach ($migrations as $file) {
            $src = file_get_contents($file);
            if (preg_match_all('/Schema::(create|table)\s*\(\s*[\'"]([^\'"]+)[\'"]/', $src, $m)) {
                foreach ($m[2] as $table) {
                    $tables[] = $table;
                }
            }
        }

        return array_unique($tables);
    }
}
