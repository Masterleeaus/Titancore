<?php

namespace Modules\TitanCore\Filament\Resources;

class PlatformApiKeyResource
{
    public const RESOURCE_KEY = 'platform_api_keys';

    public static function navigationLabel(): string
    {
        return 'API Keys';
    }
}
