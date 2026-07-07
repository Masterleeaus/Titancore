<?php

namespace Modules\TitanCore\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PromptUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() ? $this->user()->can('titancore.manage') : false;
    }

    public function rules(): array
    {
        $id = $this->route('id');
        return [
            'title' => 'sometimes|string|max:255',
            'slug' => 'sometimes|string|max:255|alpha_dash|unique:ai_prompts,slug,' . $id,
            'content' => 'sometimes|string|min:1',
        ];
    }
}
