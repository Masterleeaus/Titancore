<?php

namespace TitanSDK\Facades;

use Illuminate\Support\Facades\Facade;

final class TitanAI extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'titansdk.ai';
    }
}
