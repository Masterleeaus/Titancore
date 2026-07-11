<?php

namespace Modules\TitanCore\Services\Engine;

class EngineLoader
{
    public function load(array $engine): array
    {
        $loaded = $engine;
        $loaded['loaded'] = true;
        $loaded['loaded_at'] = gmdate('c');

        if (! isset($loaded['status']) || ! is_string($loaded['status']) || $loaded['status'] === '') {
            $loaded['status'] = 'loaded';
        }

        return $loaded;
    }
}
