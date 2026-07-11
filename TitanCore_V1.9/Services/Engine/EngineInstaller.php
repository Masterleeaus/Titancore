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

        if ($this->shouldDispatchInstalledEvent($installed)) {
            event(new EngineInstalled($installed['id'], $installed['version'], $installed['installed_at']));
        }

        return $installed;
    }

    private function shouldDispatchInstalledEvent(array $engine): bool
    {
        return function_exists('event')
            && isset($engine['id'], $engine['version'])
            && is_string($engine['id'])
            && is_string($engine['version']);
    }
}
