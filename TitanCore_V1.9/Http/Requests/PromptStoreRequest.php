<?php

namespace Modules\TitanCore\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PromptStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() ? $this->user()->can('titancore.manage') : false;
    }

    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'slug' => 'required|string|max:255|alpha_dash|unique:ai_prompts,slug',
            'content' => 'required|string|min:1',
        ];
    }
}
