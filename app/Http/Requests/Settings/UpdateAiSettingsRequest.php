<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAiSettingsRequest extends FormRequest
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
            'provider' => ['required', 'string', Rule::in(['openai'])],
            'default_model' => ['required', 'string', 'max:64'],
            'advanced_model' => ['nullable', 'string', 'max:64'],
            'api_key' => ['nullable', 'string', 'max:256'],
            'temperature' => ['required', 'numeric', 'min:0', 'max:2'],
            'max_tokens' => ['required', 'integer', 'min:1', 'max:128000'],
            'timeout_seconds' => ['required', 'integer', 'min:5', 'max:300'],
            'enable_narrative' => ['sometimes', 'boolean'],
            'narrative_min_confidence' => ['required', 'integer', 'min:0', 'max:100'],
            'enable_standardization_assist' => ['sometimes', 'boolean'],
            'rate_limit_per_user_per_hour' => ['required', 'integer', 'min:1', 'max:1000'],
            'monthly_token_budget' => ['nullable', 'integer', 'min:0'],
            'system_prompt_version' => ['required', 'string', 'max:32'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'enable_narrative' => $this->boolean('enable_narrative'),
            'narrative_min_confidence' => $this->input('narrative_min_confidence', 50),
            'enable_standardization_assist' => $this->boolean('enable_standardization_assist'),
            'monthly_token_budget' => $this->filled('monthly_token_budget') ? $this->input('monthly_token_budget') : null,
        ]);
    }
}
