<?php

namespace Modules\TitanCore\Services\Engine;

use Modules\TitanCore\Events\EngineLifecycleChanged;

class EngineLifecycle
{
    /**
     * Allowed lifecycle states for runtime engines.
     *
     * States are intentionally additive and coarse-grained:
     * - registered: discovered in metadata but not yet installed
     * - installed: installation completed
     * - loaded: runtime class resolved and ready
     * - running: actively serving requests
     * - stopped: available but not running
     * - disabled: administratively disabled
     */
    private const STATES = ['registered', 'installed', 'loaded', 'running', 'stopped', 'disabled'];

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
