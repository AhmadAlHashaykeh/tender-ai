<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class UpdateStandardizationSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'drug_auto_approve_min' => ['required', 'integer', 'min:0', 'max:100'],
            'company_auto_approve_min' => ['required', 'integer', 'min:0', 'max:100'],
            'row_auto_approve_min' => ['required', 'integer', 'min:0', 'max:100'],
            'ai_auto_approve_min' => ['required', 'integer', 'min:0', 'max:100'],
            'fuzzy_auto_approve_min' => ['required', 'integer', 'min:0', 'max:100'],
            'max_ai_calls_per_batch' => ['required', 'integer', 'min:1', 'max:10000'],
            'enable_ai_assist' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'enable_ai_assist' => $this->boolean('enable_ai_assist'),
        ]);
    }
}
