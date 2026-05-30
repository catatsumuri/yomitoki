<?php

namespace App\Http\Requests\Api;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateScrapRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:255'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:80', 'regex:/^[a-z0-9-]+$/'],
            'content_markdown' => ['sometimes', 'string'],
            'status' => ['sometimes', 'string', 'in:raw,processed,archived'],
            'source_type' => ['sometimes', 'string', 'in:plan,note,result,research,meeting,execution'],
            'summary' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
