<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class IngestScrapRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'content_markdown' => ['required', 'string'],
            'source_type' => ['sometimes', 'string', 'in:plan,note,result,research,meeting,execution'],
            'project' => ['sometimes', 'nullable', 'string', 'max:255'],
            'directory' => ['sometimes', 'nullable', 'string', 'max:500'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:80', 'regex:/^[a-z0-9-]+$/'],
            'meta' => ['sometimes', 'array'],
            'parent_slug' => ['sometimes', 'nullable', 'string', 'max:80'],
            'description' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
