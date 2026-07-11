<?php

namespace Modules\TitanCore\Entities;

use Illuminate\Database\Eloquent\Model;
use Modules\TitanCore\Entities\Concerns\TenantScoped;

class AiKbCollectionDoc extends Model
{
    use TenantScoped;

    protected $table = 'ai_kb_collection_docs';
    protected $guarded = [];
}
