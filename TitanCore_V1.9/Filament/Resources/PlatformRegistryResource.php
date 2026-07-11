<?php

namespace Modules\TitanCore\Filament\Resources;

class PlatformRegistryResource
{
    public const RESOURCE_KEY = 'platform_registry';

    public static function navigationLabel(): string
    {
        return 'Registry';
    }
}
