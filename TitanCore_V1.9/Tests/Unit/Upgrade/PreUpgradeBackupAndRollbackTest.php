<?php

namespace Modules\TitanCore\Tests\Unit\Upgrade;

use Modules\TitanCore\Services\Upgrade\PreUpgradeBackupJob;
use Modules\TitanCore\Services\Upgrade\UpgradeRollbackRunner;
use PHPUnit\Framework\TestCase;

class PreUpgradeBackupAndRollbackTest extends TestCase
{
    private string $tmpDir;
    private string $backupRoot;
    private string $moduleDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmpDir    = sys_get_temp_dir() . '/titan_backup_test_' . uniqid();
        $this->backupRoot = $this->tmpDir . '/backups';
        $this->moduleDir  = $this->tmpDir . '/module';

        mkdir($this->moduleDir, 0755, true);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->rrmdir($this->tmpDir);
    }

    // ── PreUpgradeBackupJob ───────────────────────────────────────────────────

    public function test_backup_creates_snapshot_directory(): void
    {
        // Seed some PHP files in the fake module dir
        file_put_contents($this->moduleDir . '/Foo.php', '<?php class Foo {}');
        file_put_contents($this->moduleDir . '/module.json', '{"name":"Test"}');

        $job         = new PreUpgradeBackupJob($this->backupRoot);
        $snapshotDir = $job->run('TestModule', $this->moduleDir, []);

        $this->assertDirectoryExists($snapshotDir);
        $this->assertFileExists($snapshotDir . '/snapshot.json');
    }

    public function test_backup_copies_php_and_json_files(): void
    {
        file_put_contents($this->moduleDir . '/Foo.php', '<?php class Foo {}');
        file_put_contents($this->moduleDir . '/config.json', '{}');
        file_put_contents($this->moduleDir . '/asset.css', '.a{}');  // should NOT be copied

        $job         = new PreUpgradeBackupJob($this->backupRoot);
        $snapshotDir = $job->run('TestModule', $this->moduleDir, []);

        $this->assertFileExists($snapshotDir . '/files/Foo.php');
        $this->assertFileExists($snapshotDir . '/files/config.json');
        $this->assertFalse(file_exists($snapshotDir . '/files/asset.css'));
    }

    public function test_backup_snapshot_json_has_correct_metadata(): void
    {
        $job         = new PreUpgradeBackupJob($this->backupRoot);
        $snapshotDir = $job->run('CleaningJobs', $this->moduleDir, ['work_orders']);

        $meta = json_decode(file_get_contents($snapshotDir . '/snapshot.json'), true);

        $this->assertSame('CleaningJobs', $meta['module']);
        $this->assertContains('work_orders', $meta['tables']);
        $this->assertSame($this->moduleDir, $meta['module_path']);
    }

    // ── UpgradeRollbackRunner ─────────────────────────────────────────────────

    public function test_rollback_returns_error_for_missing_snapshot(): void
    {
        $runner = new UpgradeRollbackRunner();
        $result = $runner->rollback('/non/existent/path');

        $this->assertFalse($result['ok']);
        $this->assertNotEmpty($result['errors']);
        $this->assertStringContainsString('not found', $result['errors'][0]);
    }

    public function test_rollback_restores_files_from_snapshot(): void
    {
        // Create a fake backup snapshot
        $snapshotDir = $this->tmpDir . '/snap';
        mkdir($snapshotDir . '/files/sub', 0755, true);
        file_put_contents($snapshotDir . '/files/Foo.php', '<?php class Original {}');
        file_put_contents($snapshotDir . '/snapshot.json', json_encode([
            'module'      => 'TestModule',
            'timestamp'   => date('c'),
            'tables'      => [],
            'module_path' => $this->moduleDir,
        ]));

        // Modify the live file to simulate upgrade damage
        file_put_contents($this->moduleDir . '/Foo.php', '<?php class Damaged {}');

        $runner = new UpgradeRollbackRunner();
        $result = $runner->rollback($snapshotDir, ['db' => false, 'files' => true]);

        $this->assertTrue($result['ok'], implode('; ', $result['errors']));
        $this->assertSame(1, $result['restored_files']);
        $this->assertStringContainsString('Original', file_get_contents($this->moduleDir . '/Foo.php'));
    }

    // ─────────────────────────────────────────────────────────────────────────

    private function rrmdir(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            is_dir($path) ? $this->rrmdir($path) : unlink($path);
        }
        rmdir($dir);
    }
}
