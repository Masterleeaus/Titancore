<?php

namespace Modules\TitanCore\Tests\Unit;

use Modules\TitanCore\Services\PlatformDiagnosticsService;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the PlatformDiagnosticsService (Phase 6 — Diagnostics).
 */
class PlatformDiagnosticsServiceTest extends TestCase
{
    // ── registration ─────────────────────────────────────────────────────────

    public function test_register_and_has_domain(): void
    {
        $service = new PlatformDiagnosticsService();
        $service->register('runtime', 'PHP Version', fn () => ['ok' => true, 'label' => 'PHP Version', 'detail' => 'ok']);

        $this->assertTrue($service->hasDomain('runtime'));
        $this->assertFalse($service->hasDomain('nonexistent'));
    }

    public function test_domains_returns_registered_keys(): void
    {
        $service = new PlatformDiagnosticsService();
        $service->register('runtime',  'Check A', fn () => ['ok' => true, 'label' => 'A', 'detail' => '']);
        $service->register('provider', 'Check B', fn () => ['ok' => true, 'label' => 'B', 'detail' => '']);

        $this->assertEqualsCanonicalizing(['runtime', 'provider'], $service->domains());
    }

    // ── runDomain ─────────────────────────────────────────────────────────────

    public function test_run_domain_all_checks_pass(): void
    {
        $service = new PlatformDiagnosticsService();
        $service->register('runtime', 'Check A', fn () => ['ok' => true,  'label' => 'Check A', 'detail' => 'Passed']);
        $service->register('runtime', 'Check B', fn () => ['ok' => true,  'label' => 'Check B', 'detail' => 'Passed']);

        $result = $service->runDomain('runtime');

        $this->assertTrue($result['ok']);
        $this->assertCount(2, $result['results']);
        $this->assertEmpty($result['suggestions']);
    }

    public function test_run_domain_failed_check_marks_domain_not_ok(): void
    {
        $service = new PlatformDiagnosticsService();
        $service->register('runtime', 'Failing Check', fn () => [
            'ok'         => false,
            'label'      => 'Failing Check',
            'detail'     => 'Something broke',
            'suggestion' => 'Restart the service',
        ]);

        $result = $service->runDomain('runtime');

        $this->assertFalse($result['ok']);
        $this->assertContains('Restart the service', $result['suggestions']);
    }

    public function test_run_domain_check_throws_exception_marked_failed(): void
    {
        $service = new PlatformDiagnosticsService();
        $service->register('provider', 'Throwing Check', function () {
            throw new \RuntimeException('Connection refused');
        });

        $result = $service->runDomain('provider');

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('Connection refused', $result['results'][0]['detail']);
    }

    public function test_run_domain_empty_when_no_checks_registered(): void
    {
        $service = new PlatformDiagnosticsService();

        $result = $service->runDomain('knowledge');

        $this->assertTrue($result['ok']);
        $this->assertEmpty($result['results']);
    }

    // ── runAll ────────────────────────────────────────────────────────────────

    public function test_run_all_covers_all_registered_domains(): void
    {
        $service = new PlatformDiagnosticsService();
        $service->register('runtime',   'A', fn () => ['ok' => true,  'label' => 'A', 'detail' => '']);
        $service->register('sdk',       'B', fn () => ['ok' => false, 'label' => 'B', 'detail' => 'fail']);

        $all = $service->runAll();

        $this->assertArrayHasKey('runtime', $all);
        $this->assertArrayHasKey('sdk', $all);
        $this->assertTrue($all['runtime']['ok']);
        $this->assertFalse($all['sdk']['ok']);
    }
}
