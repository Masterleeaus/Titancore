<?php

namespace Modules\TitanCore\Tests\Unit\Upgrade;

use Modules\TitanCore\Services\Upgrade\VersionCompatibilityChecker;
use PHPUnit\Framework\TestCase;

class VersionCompatibilityCheckerTest extends TestCase
{
    private VersionCompatibilityChecker $checker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->checker = new VersionCompatibilityChecker();
    }

    // ── satisfies() ───────────────────────────────────────────────────────────

    public function test_wildcard_always_passes(): void
    {
        $this->assertTrue($this->checker->satisfies('8.2.0', '*'));
        $this->assertTrue($this->checker->satisfies('11.0.0', '*'));
    }

    public function test_caret_major_range(): void
    {
        $this->assertTrue($this->checker->satisfies('8.2.0', '^8.1'));
        $this->assertTrue($this->checker->satisfies('8.3.6', '^8.1'));
        $this->assertFalse($this->checker->satisfies('9.0.0', '^8.1'));
        $this->assertFalse($this->checker->satisfies('7.4.0', '^8.1'));
    }

    public function test_tilde_minor_range(): void
    {
        $this->assertTrue($this->checker->satisfies('8.2.5', '~8.2'));
        $this->assertFalse($this->checker->satisfies('8.3.0', '~8.2'));
        $this->assertFalse($this->checker->satisfies('8.1.0', '~8.2'));
    }

    public function test_gte_constraint(): void
    {
        $this->assertTrue($this->checker->satisfies('11.5.0', '>=11.0'));
        $this->assertFalse($this->checker->satisfies('10.9.0', '>=11.0'));
    }

    public function test_exact_version(): void
    {
        $this->assertTrue($this->checker->satisfies('8.2.0', '8.2.0'));
        $this->assertFalse($this->checker->satisfies('8.2.1', '8.2.0'));
    }

    public function test_empty_constraint_passes(): void
    {
        $this->assertTrue($this->checker->satisfies('8.2.0', ''));
    }

    // ── check() ───────────────────────────────────────────────────────────────

    public function test_check_passes_when_no_constraints(): void
    {
        $result = $this->checker->check([]);
        $this->assertTrue($result['ok']);
        $this->assertEmpty($result['errors']);
    }

    public function test_check_fails_on_incompatible_php(): void
    {
        // Use a constraint that will never match the current PHP version
        $result = $this->checker->check(['requires_php' => '^5.6']);
        $this->assertFalse($result['ok']);
        $this->assertNotEmpty($result['errors']);
        $this->assertStringContainsString('PHP', $result['errors'][0]);
    }

    public function test_check_passes_on_compatible_php(): void
    {
        // Current PHP version should satisfy ^7.0 or higher with *
        $result = $this->checker->check(['requires_php' => '*']);
        $this->assertTrue($result['ok']);
    }

    public function test_check_fails_on_incompatible_laravel(): void
    {
        $result = $this->checker->check(['requires_laravel' => '^1.0']);
        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('Laravel', $result['errors'][0]);
    }

    public function test_check_passes_on_compatible_laravel(): void
    {
        $result = $this->checker->check(['requires_laravel' => '*']);
        $this->assertTrue($result['ok']);
    }
}
