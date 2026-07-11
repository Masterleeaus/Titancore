<?php

namespace Modules\TitanCore\Services\Engine;

use Modules\TitanCore\Events\EngineLifecycleChanged;

class EngineLifecycle
{
    /**
     * Allowed lifecycle states for runtime engines.
     *
     * Canonical states:
     * - installed
     * - registered
     * - validated
     * - initialized
     * - ready
     * - active
     * - maintenance
     * - upgrading
     * - disabled
     * - failed
     * - removed
     *
     * Backward-compatible aliases retained:
     * - loaded (initialized)
     * - running (active)
     * - stopped (disabled)
     */
    private const STATES = [
        'installed',
        'registered',
        'validated',
        'initialized',
        'ready',
        'active',
        'maintenance',
        'upgrading',
        'disabled',
        'failed',
        'removed',
        'loaded',
        'running',
        'stopped',
    ];

    public function states(): array
    {
        return self::STATES;
    }

    public function transition(array $engine, string $to): array
    {
        if (! in_array($to, self::STATES, true)) {
            return $engine;
        }

        $updated = $engine;
        $from = (string) ($engine['status'] ?? 'registered');
        $updated['status'] = $to;
        $updated['lifecycle_updated_at'] = gmdate('c');

        if (function_exists('event') && isset($updated['id']) && is_string($updated['id'])) {
            event(new EngineLifecycleChanged($updated['id'], $from, $to, $updated['lifecycle_updated_at']));
        }

        return $updated;
    }
}
