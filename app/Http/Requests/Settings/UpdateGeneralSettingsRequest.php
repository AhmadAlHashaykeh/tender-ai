<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGeneralSettingsRequest extends FormRequest
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
            'organization_name' => ['required', 'string', 'max:255'],
            'default_currency' => ['required', 'string', 'exists:currencies,code'],
            'date_format' => ['required', 'string', 'max:32', 'regex:/^[Ymd\-\/\.\s:,]+$/'],
            'rows_per_page' => ['required', 'integer', Rule::in([25, 50, 100])],
            'timezone' => ['required', 'string', 'timezone:all'],
            'language' => ['required', 'string', 'max:10', Rule::in(['en', 'ar'])],
        ];
    }
}
