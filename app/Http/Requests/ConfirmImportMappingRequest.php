<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmImportMappingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'mapping' => ['required', 'array'],
            'mapping.*' => ['nullable', 'integer', 'min:0'],
            'ignored_columns' => ['nullable', 'array'],
            'template_name' => ['nullable', 'string', 'max:120'],
        ];
    }
}
