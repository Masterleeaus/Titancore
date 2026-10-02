<?php

namespace Modules\TitanCore\Services;

/**
 * PlatformDiagnosticsService — platform-wide diagnostic runner.
 *
 * Each diagnostic domain runs a set of checks and returns a structured result.
 * Domains and their permissions are declared in config 'titancore.filament.diagnostics'.
 *
 * A "check" is a callable (or named closure) that returns:
 *   array{ok: bool, label: string, detail: string}
 *
 * Checks are registered per domain at runtime by kernel service providers or
 * Engine module providers.
 */
class PlatformDiagnosticsService
{
    /** @var array<string, array<int, array{label: string, check: callable}>> */
    private array $checks = [];

    /**
     * Register a diagnostic check for the given domain.
     *
     * @param  callable(): array{ok: bool, label: string, detail: string}  $check
     */
    public function register(string $domain, string $label, callable $check): void
    {
        $this->checks[$domain][] = ['label' => $label, 'check' => $check];
    }

    /**
     * Run all registered checks for a domain and return structured results.
     *
     * @return array{
     *   domain: string,
     *   ok: bool,
     *   results: array<int, array{ok: bool, label: string, detail: string}>,
     *   suggestions: string[],
     * }
     */
    public function runDomain(string $domain): array
    {
        $results     = [];
        $allOk       = true;
        $suggestions = [];

        foreach ($this->checks[$domain] ?? [] as $entry) {
            try {
                $result    = ($entry['check'])();
                $result    = $this->normalise($result, $entry['label']);
                $results[] = $result;

                if (! $result['ok']) {
                    $allOk = false;
                    if (! empty($result['suggestion'])) {
                        $suggestions[] = $result['suggestion'];
                    }
                }
            } catch (\Throwable $e) {
                $results[] = [
                    'ok'         => false,
                    'label'      => $entry['label'],
                    'detail'     => 'Check threw an exception: ' . $e->getMessage(),
                    'suggestion' => 'Inspect the service behind this check for errors.',
                ];
                $allOk = false;
            }
        }

        return [
            'domain'      => $domain,
            'ok'          => $allOk,
            'results'     => $results,
            'suggestions' => $suggestions,
        ];
    }

    /**
     * Run all registered domains and return an indexed result set.
     *
     * @return array<string, array{domain: string, ok: bool, results: array, suggestions: string[]}>
     */
    public function runAll(): array
    {
        $output = [];

        foreach (array_keys($this->checks) as $domain) {
            $output[$domain] = $this->runDomain($domain);
        }

        return $output;
    }

    /**
     * Return the list of registered domain keys.
     *
     * @return string[]
     */
    public function domains(): array
    {
        return array_keys($this->checks);
    }

    /**
     * Return whether any checks are registered for a domain.
     */
    public function hasDomain(string $domain): bool
    {
        return ! empty($this->checks[$domain]);
    }

    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Normalise a check result to ensure all keys are present.
     *
     * @param  array<string, mixed>  $result
     * @return array{ok: bool, label: string, detail: string, suggestion: string}
     */
    private function normalise(array $result, string $fallbackLabel): array
    {
        return [
            'ok'         => (bool) ($result['ok'] ?? false),
            'label'      => isset($result['label']) ? (string) $result['label'] : $fallbackLabel,
            'detail'     => (string) ($result['detail'] ?? ''),
            'suggestion' => (string) ($result['suggestion'] ?? ''),
        ];
    }
}
