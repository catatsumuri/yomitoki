<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class UploadScrapImageRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'image' => [
                'required',
                File::types([
                    'jpg',
                    'jpeg',
                    'png',
                    'gif',
                    'webp',
                    'pdf',
                    'doc',
                    'docx',
                    'md',
                    'txt',
                ])->max(10 * 1024),
            ],
            'upload_key' => ['nullable', 'string', 'max:64'],
        ];
    }
}
