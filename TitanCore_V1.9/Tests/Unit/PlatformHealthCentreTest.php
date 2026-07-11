<?php

namespace Modules\TitanCore\Tests\Unit;

use Modules\TitanCore\Services\PlatformHealthCentre;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for PlatformHealthCentre (Phase 7 — Health Centre).
 */
class PlatformHealthCentreTest extends TestCase
{
    // ── registration ─────────────────────────────────────────────────────────

    public function test_register_and_domains(): void
    {
        $centre = new PlatformHealthCentre();
        $centre->register('runtime', 'Runtime', fn () => ['ok' => true, 'status' => 'healthy', 'message' => 'OK']);
        $centre->register('provider', 'Provider', fn () => ['ok' => true, 'status' => 'healthy', 'message' => 'OK']);

        $this->assertEqualsCanonicalizing(['runtime', 'provider'], $centre->domains());
    }

    // ── poll ─────────────────────────────────────────────────────────────────

    public function test_poll_healthy_reporter(): void
    {
        $centre = new PlatformHealthCentre();
        $centre->register('runtime', 'Runtime', fn () => [
            'ok'      => true,
            'status'  => PlatformHealthCentre::STATUS_HEALTHY,
            'message' => 'All good',
            'metrics' => ['latency_ms' => 5],
        ]);

        $result = $centre->poll('runtime');

        $this->assertTrue($result['ok']);
        $this->assertSame(PlatformHealthCentre::STATUS_HEALTHY, $result['status']);
        $this->assertSame(['latency_ms' => 5], $result['metrics']);
    }

    public function test_poll_unknown_domain_returns_unknown_status(): void
    {
        $centre = new PlatformHealthCentre();

        $result = $centre->poll('nonexistent');

        $this->assertFalse($result['ok']);
        $this->assertSame(PlatformHealthCentre::STATUS_UNKNOWN, $result['status']);
    }

    public function test_poll_reporter_that_throws_returns_unknown(): void
    {
        $centre = new PlatformHealthCentre();
        $centre->register('engine', 'Engine', function () {
            throw new \RuntimeException('Service down');
        });

        $result = $centre->poll('engine');

        $this->assertFalse($result['ok']);
        $this->assertSame(PlatformHealthCentre::STATUS_UNKNOWN, $result['status']);
        $this->assertStringContainsString('Service down', $result['message']);
    }

    // ── aggregate ────────────────────────────────────────────────────────────

    public function test_aggregate_all_healthy(): void
    {
        $centre = new PlatformHealthCentre();
        $centre->register('runtime',  'Runtime',  fn () => ['ok' => true, 'status' => 'healthy',  'message' => 'OK']);
        $centre->register('provider', 'Provider', fn () => ['ok' => true, 'status' => 'healthy',  'message' => 'OK']);

        $summary = $centre->aggregate();

        $this->assertTrue($summary['ok']);
        $this->assertSame(PlatformHealthCentre::STATUS_HEALTHY, $summary['overall']);
        $this->assertEmpty($summary['warnings']);
        $this->assertEmpty($summary['critical']);
    }

    public function test_aggregate_with_degraded_domain(): void
    {
        $centre = new PlatformHealthCentre();
        $centre->register('runtime', 'Runtime', fn () => ['ok' => false, 'status' => 'degraded', 'message' => 'Slow queries']);

        $summary = $centre->aggregate();

        $this->assertFalse($summary['ok']);
        $this->assertSame(PlatformHealthCentre::STATUS_DEGRADED, $summary['overall']);
        $this->assertNotEmpty($summary['warnings']);
    }

    public function test_aggregate_with_critical_domain(): void
    {
        $centre = new PlatformHealthCentre();
        $centre->register('runtime',  'Runtime',  fn () => ['ok' => false, 'status' => 'critical', 'message' => 'Down']);
        $centre->register('provider', 'Provider', fn () => ['ok' => true,  'status' => 'healthy',  'message' => 'OK']);

        $summary = $centre->aggregate();

        $this->assertFalse($summary['ok']);
        $this->assertSame(PlatformHealthCentre::STATUS_CRITICAL, $summary['overall']);
        $this->assertNotEmpty($summary['critical']);
    }

    // ── history ───────────────────────────────────────────────────────────────

    public function test_history_recorded_after_poll(): void
    {
        $centre = new PlatformHealthCentre();
        $centre->register('runtime', 'Runtime', fn () => ['ok' => true, 'status' => 'healthy', 'message' => 'OK']);

        $centre->poll('runtime');
        $centre->poll('runtime');

        $history = $centre->history('runtime');

        $this->assertCount(2, $history);
    }

    public function test_history_capped_at_max_history(): void
    {
        $centre = new PlatformHealthCentre(maxHistory: 3);
        $centre->register('runtime', 'Runtime', fn () => ['ok' => true, 'status' => 'healthy', 'message' => 'OK']);

        for ($i = 0; $i < 5; $i++) {
            $centre->poll('runtime');
        }

        $this->assertCount(3, $centre->history('runtime'));
    }

    public function test_history_empty_for_unpolled_domain(): void
    {
        $centre = new PlatformHealthCentre();
        $centre->register('runtime', 'Runtime', fn () => ['ok' => true, 'status' => 'healthy', 'message' => 'OK']);

        $this->assertEmpty($centre->history('runtime'));
    }
}
