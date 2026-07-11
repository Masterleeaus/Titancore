<?php

namespace Modules\TitanCore\Services\Engine;

use Modules\TitanCore\Events\EngineInstalled;

class EngineInstaller
{
    public function install(array $engine): array
    {
        $installed = $engine;
        $installed['status'] = 'installed';
        $installed['installed_at'] = gmdate('c');

        if (function_exists('event') && isset($installed['id'], $installed['version']) && is_string($installed['id']) && is_string($installed['version'])) {
            event(new EngineInstalled($installed['id'], $installed['version'], $installed['installed_at']));
        }

        return $installed;
    }
}
