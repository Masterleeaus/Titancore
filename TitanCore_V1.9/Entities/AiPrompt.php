<?php

namespace Modules\TitanCore\Entities;

use Illuminate\Database\Eloquent\Model;
use Modules\TitanCore\Entities\Concerns\TenantScoped;

class AiPrompt extends Model
{
    use TenantScoped;

    protected $table = 'ai_prompts';
    protected $guarded = [];
}
