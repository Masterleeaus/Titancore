<?php

namespace Modules\TitanCore\Filament\Resources;

class PlatformModelResource
{
    public const RESOURCE_KEY = 'platform_models';

    public static function navigationLabel(): string
    {
        return 'Models';
    }
}
