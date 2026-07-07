<?php

namespace Modules\TitanCore\Tests\Unit\Upgrade;

use Modules\TitanCore\Services\Upgrade\DependencyChecker;
use Modules\TitanCore\Services\Upgrade\VersionCompatibilityChecker;
use PHPUnit\Framework\TestCase;

class DependencyCheckerTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmpDir = sys_get_temp_dir() . '/titan_dep_test_' . uniqid();
        mkdir($this->tmpDir, 0755, true);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->rrmdir($this->tmpDir);
    }

    private function makeModuleManifest(string $name, string $version, string $alias = ''): void
    {
        $dir = $this->tmpDir . '/' . $name;
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents(
            $dir . '/module.json',
            json_encode(['name' => $name, 'alias' => $alias ?: strtolower($name), 'version' => $version])
        );
    }

    private function checker(): DependencyChecker
    {
        return new DependencyChecker(new VersionCompatibilityChecker(), $this->tmpDir);
    }

    // ─────────────────────────────────────────────────────────────────────────

    public function test_passes_when_no_dependencies(): void
    {
        $result = $this->checker()->check([]);
        $this->assertTrue($result['ok']);
        $this->assertEmpty($result['errors']);
    }

    public function test_passes_with_list_form_deps_installed(): void
    {
        $this->makeModuleManifest('TitanCore', '1.0.0');
        $this->makeModuleManifest('Accountings', '2.3.0');

        $result = $this->checker()->check(['requires' => ['TitanCore', 'Accountings']]);
        $this->assertTrue($result['ok']);
    }

    public function test_fails_when_required_module_missing(): void
    {
        $result = $this->checker()->check(['requires' => ['MissingModule']]);
        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('MissingModule', $result['errors'][0]);
    }

    public function test_passes_with_map_form_deps_and_version_satisfied(): void
    {
        $this->makeModuleManifest('TitanCore', '1.5.0');

        $result = $this->checker()->check(['requires' => ['TitanCore' => '^1.0']]);
        $this->assertTrue($result['ok']);
    }

    public function test_fails_with_map_form_deps_and_version_not_satisfied(): void
    {
        $this->makeModuleManifest('TitanCore', '0.9.0');

        $result = $this->checker()->check(['requires' => ['TitanCore' => '^1.0']]);
        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('TitanCore', $result['errors'][0]);
    }

    public function test_wildcard_constraint_always_passes(): void
    {
        $this->makeModuleManifest('TitanCore', '0.1.0');

        $result = $this->checker()->check(['requires' => ['TitanCore' => '*']]);
        $this->assertTrue($result['ok']);
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
