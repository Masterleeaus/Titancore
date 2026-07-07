<?php

namespace Modules\TitanCore\Entities\Concerns;

use Illuminate\Database\Eloquent\Builder;

trait TenantScoped
{
    public static function bootTenantScoped(): void
    {
        static::addGlobalScope('titancore_tenant', function (Builder $builder) {
            $resolver = config('titancore.tenant_resolver');
            if (is_callable($resolver)) {
                $tenantId = call_user_func($resolver);
                if ($tenantId !== null) {
                    $builder->where((new static)->getTable().'.tenant_id', '=', $tenantId);
                }
            }
        });
    }
}
