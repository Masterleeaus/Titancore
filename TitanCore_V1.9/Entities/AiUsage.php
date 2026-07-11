<?php

namespace Modules\TitanCore\Entities;

use Illuminate\Database\Eloquent\Model;
use Modules\TitanCore\Entities\Concerns\TenantScoped;

class AiUsage extends Model
{
    use TenantScoped;

    protected $table = 'ai_usage';
    protected $guarded = [];
}
