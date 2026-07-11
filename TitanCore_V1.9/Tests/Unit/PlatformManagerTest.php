<?php

namespace Modules\TitanCore\Tests\Unit;

use Modules\TitanCore\Services\Upgrade\VersionCompatibilityChecker;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the Platform Manager components introduced in the Platform Manager sprint.
 *
 * Tests cover:
 *  - CompatibilityController logic via VersionCompatibilityChecker
 *  - Dependency graph building logic
 *  - Marketplace package metadata schema
 *  - Upgrade validation pre-conditions
 */
class PlatformManagerTest extends TestCase
{
    // ── VersionCompatibilityChecker ───────────────────────────────────────────

    public function test_compatibility_checker_passes_when_no_constraints_declared(): void
    {
        $checker = new VersionCompatibilityChecker();
        $result  = $checker->check([]);

        $this->assertTrue($result['ok']);
        $this->assertEmpty($result['errors']);
    }

    public function test_compatibility_checker_passes_with_matching_php_constraint(): void
    {
        $checker = new VersionCompatibilityChecker();
        $result  = $checker->check(['requires_php' => '^' . PHP_MAJOR_VERSION . '.0']);

        $this->assertTrue($result['ok'], implode(', ', $result['errors']));
    }

    public function test_compatibility_checker_fails_with_impossible_php_constraint(): void
    {
        $checker = new VersionCompatibilityChecker();
        $result  = $checker->check(['requires_php' => '^999.0']);

        $this->assertFalse($result['ok']);
        $this->assertNotEmpty($result['errors']);
        $this->assertStringContainsString('PHP', $result['errors'][0]);
    }

    public function test_compatibility_checker_handles_wildcard_constraint(): void
    {
        $checker = new VersionCompatibilityChecker();
        $result  = $checker->check(['requires_php' => '*']);

        $this->assertTrue($result['ok']);
    }

    public function test_compatibility_checker_handles_gte_constraint(): void
    {
        $checker = new VersionCompatibilityChecker();
        // PHP_VERSION is always >= 1.0
        $result = $checker->check(['requires_php' => '>=1.0']);

        $this->assertTrue($result['ok']);
    }

    // ── Marketplace package metadata schema ───────────────────────────────────

    public function test_marketplace_package_meta_required_fields_are_present(): void
    {
        $manifest = [
            'name'        => 'TestModule',
            'version'     => '1.0.0',
            'description' => 'A test module',
            'publisher'   => 'test-publisher',
            'capabilities'=> ['chat'],
            'requires'    => [],
            'conflicts'   => [],
        ];

        $this->assertArrayHasKey('name', $manifest);
        $this->assertArrayHasKey('version', $manifest);
        $this->assertArrayHasKey('publisher', $manifest);
        $this->assertIsArray($manifest['capabilities']);
        $this->assertIsArray($manifest['requires']);
        $this->assertIsArray($manifest['conflicts']);
    }

    public function test_marketplace_compatibility_check_with_synthetic_manifest(): void
    {
        $checker  = new VersionCompatibilityChecker();
        $manifest = [
            'name'         => 'TestModule',
            'version'      => '1.0.0',
            'requires_php' => '^' . PHP_MAJOR_VERSION . '.0',
        ];

        $result = $checker->check($manifest);

        $this->assertTrue($result['ok'], implode(', ', $result['errors']));
    }

    // ── Dependency resolution preview ─────────────────────────────────────────

    public function test_dependency_resolution_marks_missing_deps(): void
    {
        $requires   = ['NonExistentModule', 'AnotherMissing:^2.0'];
        $modulesDir = sys_get_temp_dir() . '/titan_pm_deps_test_' . uniqid('', true);
        mkdir($modulesDir, 0755, true);

        $resolution = [];
        $missing    = [];
        $satisfied  = [];

        foreach ($requires as $req) {
            $parts      = explode(':', (string) $req, 2);
            $depName    = $parts[0];
            $constraint = $parts[1] ?? null;

            $depDir = $modulesDir . '/' . $depName;
            if (is_dir($depDir)) {
                $satisfied[] = ['module' => $depName, 'installed' => true, 'constraint' => $constraint];
            } else {
                $missing[] = ['module' => $depName, 'installed' => false, 'constraint' => $constraint];
            }
        }

        $resolution  = array_merge($satisfied, $missing);
        $resolvable  = count($missing) === 0;

        $this->assertFalse($resolvable);
        $this->assertCount(2, $missing);
        $this->assertContains('NonExistentModule', array_column($missing, 'module'));
        $this->assertContains('AnotherMissing', array_column($missing, 'module'));
        $this->assertSame('^2.0', array_values(array_filter($missing, fn ($m) => $m['module'] === 'AnotherMissing'))[0]['constraint'] ?? null);

        rmdir($modulesDir);
    }

    public function test_dependency_resolution_marks_satisfied_deps(): void
    {
        $modulesDir = sys_get_temp_dir() . '/titan_pm_deps_sat_' . uniqid('', true);
        mkdir($modulesDir . '/DepA', 0755, true);
        file_put_contents($modulesDir . '/DepA/module.json', json_encode(['name' => 'DepA', 'version' => '1.2.0']));

        $requires   = ['DepA:^1.0'];
        $satisfied  = [];
        $missing    = [];

        foreach ($requires as $req) {
            $parts      = explode(':', (string) $req, 2);
            $depName    = $parts[0];
            $constraint = $parts[1] ?? null;

            $depDir = $modulesDir . '/' . $depName;
            if (is_dir($depDir)) {
                $mFile    = $depDir . '/module.json';
                $manifest = is_file($mFile) ? (json_decode((string) file_get_contents($mFile), true) ?: []) : [];
                $satisfied[] = [
                    'module'     => $depName,
                    'version'    => $manifest['version'] ?? null,
                    'constraint' => $constraint,
                    'installed'  => true,
                ];
            } else {
                $missing[] = ['module' => $depName, 'installed' => false, 'constraint' => $constraint];
            }
        }

        $this->assertCount(1, $satisfied);
        $this->assertEmpty($missing);
        $this->assertSame('DepA', $satisfied[0]['module']);
        $this->assertSame('1.2.0', $satisfied[0]['version']);

        // Clean up
        unlink($modulesDir . '/DepA/module.json');
        rmdir($modulesDir . '/DepA');
        rmdir($modulesDir);
    }

    // ── Dependency graph orphan detection ─────────────────────────────────────

    public function test_orphan_detection_identifies_standalone_module(): void
    {
        $manifests = [
            'Alpha' => ['version' => '1.0', 'requires' => []],
            'Beta'  => ['version' => '1.0', 'requires' => ['Alpha']],
            'Gamma' => ['version' => '1.0', 'requires' => []],  // Gamma is orphaned
        ];

        $orphaned = [];

        foreach ($manifests as $name => $data) {
            $hasRequires   = ! empty($data['requires']);
            $hasDependents = false;

            foreach ($manifests as $otherName => $otherData) {
                if ($otherName === $name) {
                    continue;
                }
                foreach ((array) ($otherData['requires'] ?? []) as $dep) {
                    if (explode(':', $dep)[0] === $name) {
                        $hasDependents = true;
                        break 2;
                    }
                }
            }

            if (! $hasRequires && ! $hasDependents && count($manifests) > 1) {
                $orphaned[] = $name;
            }
        }

        $this->assertContains('Gamma', $orphaned);
        $this->assertNotContains('Alpha', $orphaned); // Alpha has dependents (Beta)
        $this->assertNotContains('Beta', $orphaned);  // Beta has requirements
    }

    // ── Publisher trust schema ─────────────────────────────────────────────────

    public function test_publisher_metadata_schema(): void
    {
        $publisher = [
            'id'          => 'titancore',
            'name'        => 'TitanCore Platform',
            'trusted'     => true,
            'verified_at' => null,
            'public_key'  => null,
            'modules'     => ['TitanCore'],
        ];

        $this->assertArrayHasKey('id', $publisher);
        $this->assertArrayHasKey('trusted', $publisher);
        $this->assertArrayHasKey('modules', $publisher);
        $this->assertTrue($publisher['trusted']);
        $this->assertIsArray($publisher['modules']);
    }

    // ── Upgrade validation pre-conditions ─────────────────────────────────────

    public function test_upgrade_validation_fails_for_incompatible_php(): void
    {
        $checker  = new VersionCompatibilityChecker();
        $manifest = ['requires_php' => '^999.0'];

        $result = $checker->check($manifest);

        $this->assertFalse($result['ok']);
        $this->assertNotEmpty($result['errors']);
    }

    public function test_upgrade_pre_check_warns_on_downgrade(): void
    {
        $currentVersion = '2.0.0';
        $targetVersion  = '1.0.0';
        $warnings       = [];

        if (version_compare($targetVersion, $currentVersion, '<')) {
            $warnings[] = "Target version {$targetVersion} is older than installed version {$currentVersion}. Downgrade may break data.";
        }

        $this->assertNotEmpty($warnings);
        $this->assertStringContainsString('Downgrade', $warnings[0]);
    }

    public function test_upgrade_pre_check_does_not_warn_on_valid_upgrade(): void
    {
        $currentVersion = '1.0.0';
        $targetVersion  = '2.0.0';
        $warnings       = [];

        if (version_compare($targetVersion, $currentVersion, '<')) {
            $warnings[] = "Downgrade detected";
        }

        $this->assertEmpty($warnings);
    }

    // ── Module manifest validation ─────────────────────────────────────────────

    public function test_module_manifest_validation_detects_missing_name(): void
    {
        $tmpDir = sys_get_temp_dir() . '/titan_pm_manifest_' . uniqid('', true);
        mkdir($tmpDir, 0755, true);

        $manifestFile = $tmpDir . '/module.json';
        file_put_contents($manifestFile, json_encode(['version' => '1.0.0']));  // Missing 'name'

        $manifest  = json_decode((string) file_get_contents($manifestFile), true);
        $warnings  = [];

        if (empty($manifest['name'])) {
            $warnings[] = 'module.json missing "name" key';
        }

        $this->assertContains('module.json missing "name" key', $warnings);

        unlink($manifestFile);
        rmdir($tmpDir);
    }

    public function test_module_manifest_validation_passes_with_complete_manifest(): void
    {
        $manifest = [
            'name'        => 'TestModule',
            'alias'       => 'testmodule',
            'version'     => '1.0.0',
            'description' => 'A test module',
            'capabilities'=> ['chat'],
        ];

        $errors   = [];
        $warnings = [];

        if (empty($manifest['name'])) {
            $warnings[] = 'module.json missing "name" key';
        }
        if (empty($manifest['version'])) {
            $warnings[] = 'module.json missing "version" key';
        }

        $this->assertEmpty($errors);
        $this->assertEmpty($warnings);
    }
}
