<?php

namespace Modules\TitanCore\Services;

/**
 * PlatformHealthCentre — aggregates health signals from all platform domains.
 *
 * Domains register health reporters (callables) at boot. The centre polls each
 * reporter, collates results, and surfaces current status, historical trends,
 * warnings and critical failures.
 *
 * A health reporter must return:
 *   array{
 *     ok: bool,
 *     status: 'healthy'|'degraded'|'critical'|'unknown',
 *     message: string,
 *     metrics?: array<string, mixed>,
 *   }
 */
class PlatformHealthCentre
{
    public const STATUS_HEALTHY  = 'healthy';
    public const STATUS_DEGRADED = 'degraded';
    public const STATUS_CRITICAL = 'critical';
    public const STATUS_UNKNOWN  = 'unknown';

    /** @var array<string, array{label: string, reporter: callable}> */
    private array $reporters = [];

    /** @var array<string, array<int, array<string, mixed>>> History per domain (latest-first). */
    private array $history = [];

    /** Maximum history entries retained per domain. */
    private int $maxHistory;

    public function __construct(int $maxHistory = 100)
    {
        $this->maxHistory = $maxHistory;
    }

    /**
     * Register a health reporter for a named domain.
     *
     * @param  callable(): array{ok: bool, status: string, message: string, metrics?: array}  $reporter
     */
    public function register(string $domain, string $label, callable $reporter): void
    {
        $this->reporters[$domain] = ['label' => $label, 'reporter' => $reporter];
    }

    /**
     * Poll a single domain and return its health result.
     *
     * @return array{domain: string, label: string, ok: bool, status: string, message: string, metrics: array, polled_at: string}
     */
    public function poll(string $domain): array
    {
        if (! isset($this->reporters[$domain])) {
            return $this->unknownResult($domain, "Domain '{$domain}' is not registered.");
        }

        try {
            $raw    = ($this->reporters[$domain]['reporter'])();
            $result = $this->normalise($domain, $this->reporters[$domain]['label'], $raw);
        } catch (\Throwable $e) {
            $result = $this->unknownResult($domain, 'Reporter threw an exception: ' . $e->getMessage());
        }

        $this->appendHistory($domain, $result);

        return $result;
    }

    /**
     * Poll all registered domains and return an aggregate health summary.
     *
     * @return array{
     *   overall: string,
     *   ok: bool,
     *   domains: array<string, array<string, mixed>>,
     *   warnings: string[],
     *   critical: string[],
     * }
     */
    public function aggregate(): array
    {
        $domains  = [];
        $warnings = [];
        $critical = [];

        foreach (array_keys($this->reporters) as $domain) {
            $result            = $this->poll($domain);
            $domains[$domain]  = $result;

            if ($result['status'] === self::STATUS_DEGRADED) {
                $warnings[] = "[{$result['label']}] {$result['message']}";
            } elseif ($result['status'] === self::STATUS_CRITICAL) {
                $critical[] = "[{$result['label']}] {$result['message']}";
            }
        }

        $overall = $this->deriveOverallStatus($domains);

        return [
            'overall'  => $overall,
            'ok'       => $overall === self::STATUS_HEALTHY,
            'domains'  => $domains,
            'warnings' => $warnings,
            'critical' => $critical,
        ];
    }

    /**
     * Return the historical health entries for a domain (latest-first).
     *
     * @return array<int, array<string, mixed>>
     */
    public function history(string $domain): array
    {
        return array_values($this->history[$domain] ?? []);
    }

    /**
     * Return all registered domain keys.
     *
     * @return string[]
     */
    public function domains(): array
    {
        return array_keys($this->reporters);
    }

    // ──────────────────────────────────────────────────────────────────────────

    /**
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    private function normalise(string $domain, string $label, array $raw): array
    {
        $status = in_array($raw['status'] ?? '', [self::STATUS_HEALTHY, self::STATUS_DEGRADED, self::STATUS_CRITICAL], true)
            ? $raw['status']
            : self::STATUS_UNKNOWN;

        return [
            'domain'     => $domain,
            'label'      => $label,
            'ok'         => (bool) ($raw['ok'] ?? false),
            'status'     => $status,
            'message'    => (string) ($raw['message'] ?? ''),
            'metrics'    => (array) ($raw['metrics'] ?? []),
            'polled_at'  => $this->now(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function unknownResult(string $domain, string $message): array
    {
        return [
            'domain'    => $domain,
            'label'     => $domain,
            'ok'        => false,
            'status'    => self::STATUS_UNKNOWN,
            'message'   => $message,
            'metrics'   => [],
            'polled_at' => $this->now(),
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $domains
     */
    private function deriveOverallStatus(array $domains): string
    {
        $statuses = array_column(array_values($domains), 'status');

        if (in_array(self::STATUS_CRITICAL, $statuses, true)) {
            return self::STATUS_CRITICAL;
        }

        if (in_array(self::STATUS_DEGRADED, $statuses, true) || in_array(self::STATUS_UNKNOWN, $statuses, true)) {
            return self::STATUS_DEGRADED;
        }

        return empty($statuses) ? self::STATUS_UNKNOWN : self::STATUS_HEALTHY;
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function appendHistory(string $domain, array $result): void
    {
        array_unshift($this->history[$domain], $result);

        if (count($this->history[$domain]) > $this->maxHistory) {
            $this->history[$domain] = array_slice($this->history[$domain], 0, $this->maxHistory);
        }
    }

    private function now(): string
    {
        return date('Y-m-d H:i:s');
    }
}
