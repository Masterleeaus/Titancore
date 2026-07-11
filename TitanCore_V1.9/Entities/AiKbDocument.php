<?php

namespace Modules\TitanCore\Entities;

use Illuminate\Database\Eloquent\Model;
use Modules\TitanCore\Entities\Concerns\TenantScoped;

class AiKbDocument extends Model
{
    use TenantScoped;

    protected $table = 'ai_kb_documents';
    protected $guarded = [];
}
