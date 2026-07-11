<?php

namespace Modules\TitanCore\Services\Engine;

class EngineLoader
{
    public function load(array $engine): array
    {
        $loaded = $engine;
        $loaded['loaded'] = true;
        $loaded['loaded_at'] = gmdate('c');
        $loaded['status'] = 'loaded';

        return $loaded;
    }
}
