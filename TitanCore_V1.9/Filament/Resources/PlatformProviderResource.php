<?php

namespace Modules\TitanCore\Filament\Resources;

class PlatformProviderResource
{
    public const RESOURCE_KEY = 'platform_providers';

    public static function navigationLabel(): string
    {
        return 'Providers';
    }
}
