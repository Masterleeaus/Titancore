<?php

namespace Modules\TitanCore\Services\Upgrade;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

/**
 * Restores a module to its pre-upgrade state using a snapshot created by
 * PreUpgradeBackupJob.
 *
 * Rollback strategy:
 *  1. Restore DB rows for every table that was snapshotted.
 *  2. Restore module source files from the file snapshot.
 */
class UpgradeRollbackRunner
{
    /**
     * Execute rollback from a snapshot directory.
     *
     * @param  string  $snapshotDir  Path returned by PreUpgradeBackupJob::run()
     * @param  array{db?: bool, files?: bool}  $options
     * @return array{ok: bool, restored_tables: string[], restored_files: int, errors: string[]}
     */
    public function rollback(string $snapshotDir, array $options = []): array
    {
        $restoreDb    = $options['db']    ?? true;
        $restoreFiles = $options['files'] ?? true;

        $errors         = [];
        $restoredTables = [];
        $restoredFiles  = 0;

        if (! is_dir($snapshotDir)) {
            return [
                'ok'               => false,
                'restored_tables'  => [],
                'restored_files'   => 0,
                'errors'           => ["Snapshot directory not found: {$snapshotDir}"],
            ];
        }

        // Load metadata
        $meta = $this->loadMeta($snapshotDir);

        // 1. Restore DB tables
        if ($restoreDb) {
            $tablesDir = $snapshotDir . '/tables';
            if (is_dir($tablesDir)) {
                foreach (glob($tablesDir . '/*.json') ?: [] as $file) {
                    $table = pathinfo($file, PATHINFO_FILENAME);
                    try {
                        $this->restoreTable($table, $file);
                        $restoredTables[] = $table;
                    } catch (\Throwable $e) {
                        $errors[] = "Failed to restore table {$table}: " . $e->getMessage();
                    }
                }
            }
        }

        // 2. Restore source files
        if ($restoreFiles && isset($meta['module_path'])) {
            $filesDir = $snapshotDir . '/files';
            if (is_dir($filesDir)) {
                try {
                    $restoredFiles = $this->restoreFiles($filesDir, (string) $meta['module_path']);
                } catch (\Throwable $e) {
                    $errors[] = 'Failed to restore files: ' . $e->getMessage();
                }
            }
        }

        return [
            'ok'              => empty($errors),
            'restored_tables' => $restoredTables,
            'restored_files'  => $restoredFiles,
            'errors'          => $errors,
        ];
    }

    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Truncate and re-insert rows from the JSON snapshot for $table.
     */
    private function restoreTable(string $table, string $jsonFile): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        $rows = json_decode(file_get_contents($jsonFile), true);
        if (! is_array($rows)) {
            return;
        }

        DB::transaction(function () use ($table, $rows) {
            DB::table($table)->delete();
            foreach (array_chunk($rows, 500) as $chunk) {
                DB::table($table)->insert(array_map(fn($r) => (array) $r, $chunk));
            }
        });
    }

    /**
     * Copy files from $filesDir back to $modulePath, restoring originals.
     *
     * @return int  Number of files restored
     */
    private function restoreFiles(string $filesDir, string $modulePath): int
    {
        $count    = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($filesDir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            /** @var \SplFileInfo $item */
            if ($item->isDir()) {
                continue;
            }

            $relative  = ltrim(substr($item->getRealPath(), strlen($filesDir)), DIRECTORY_SEPARATOR);
            $destPath  = $modulePath . DIRECTORY_SEPARATOR . $relative;
            $destParent = dirname($destPath);

            if (! is_dir($destParent)) {
                mkdir($destParent, 0755, true);
            }

            copy($item->getRealPath(), $destPath);
            $count++;
        }

        return $count;
    }

    /**
     * @return array<string, mixed>
     */
    private function loadMeta(string $snapshotDir): array
    {
        $metaFile = $snapshotDir . '/snapshot.json';

        if (! file_exists($metaFile)) {
            return [];
        }

        $decoded = json_decode(file_get_contents($metaFile), true);

        return is_array($decoded) ? $decoded : [];
    }
}
