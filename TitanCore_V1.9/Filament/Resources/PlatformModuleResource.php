<?php

namespace Modules\TitanCore\Filament\Resources;

class PlatformModuleResource
{
    public const RESOURCE_KEY = 'platform_modules';

    public static function navigationLabel(): string
    {
        return 'Modules';
    }
}
