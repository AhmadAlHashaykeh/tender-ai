<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePredictionSettingsRequest extends FormRequest
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
            'calculation_model_version' => ['required', 'string', 'max:32'],
            'backend_only_confidence_threshold' => ['required', 'integer', 'min:0', 'max:100'],
            'trend_adjustment_cap' => ['required', 'numeric', 'min:0', 'max:50'],
            'aggressive_discount_percent' => ['required', 'numeric', 'min:0', 'max:50'],
            'conservative_premium_percent' => ['required', 'numeric', 'min:0', 'max:50'],
            'large_quantity_multiplier' => ['required', 'numeric', 'min:0.1', 'max:10'],
            'large_quantity_discount_percent' => ['required', 'numeric', 'min:0', 'max:50'],
            'small_quantity_multiplier' => ['required', 'numeric', 'min:0.01', 'max:1'],
            'small_quantity_premium_percent' => ['required', 'numeric', 'min:0', 'max:50'],
        ];
    }
}
