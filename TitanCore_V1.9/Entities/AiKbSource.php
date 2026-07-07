<?php

namespace Modules\TitanCore\Entities;

use Illuminate\Database\Eloquent\Model;
use Modules\TitanCore\Entities\Concerns\TenantScoped;

class AiKbSource extends Model
{
    use TenantScoped;

    protected $table = 'ai_kb_sources';
    protected $guarded = [];
}
