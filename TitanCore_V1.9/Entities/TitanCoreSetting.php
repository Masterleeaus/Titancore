<?php

namespace Modules\TitanCore\Entities;

use Illuminate\Database\Eloquent\Model;

class TitanCoreSetting extends Model
{
    protected $table = 'titancore_settings';

    protected $guarded = ['id'];

    protected $casts = [
        'auto_sync_kb' => 'boolean',
    ];

    public static function getSetting(): self
    {
        return static::query()->first() ?? static::create([
            'default_provider'   => config('titancore.default_provider', 'openai'),
            'daily_token_limit'  => config('titancore.quotas.per_tenant_daily_tokens', 200000),
            'auto_sync_kb'       => true,
            'kb_collection_slug' => 'worksuite_core_kb',
        ]);
    }
}
