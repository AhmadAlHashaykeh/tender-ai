<?php

namespace App\Http\Requests;

use App\Models\Tender;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StorePredictionRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $merge = [
            'quantity_unit' => filled($this->input('quantity_unit')) ? $this->input('quantity_unit') : 'units',
        ];

        $tenderId = $this->input('tender_id');
        if (filled($tenderId)) {
            $countryId = Tender::query()->whereKey((int) $tenderId)->value('country_id');
            if ($countryId !== null) {
                $merge['country_id'] = $countryId;
            }
        }

        $this->merge($merge);
    }

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
            'tender_id' => ['required', 'integer', 'exists:tenders,id'],
            'standardized_drug_id' => ['required', 'integer', 'exists:standardized_drugs,id'],
            'country_id' => ['required', 'integer', 'exists:countries,id'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'quantity_unit' => ['required', 'string', 'max:50'],
            'discount_percentage' => ['required', 'numeric', 'min:0', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'tender_id.required' => 'Please select the tender this recommendation is for.',
            'tender_id.exists' => 'The selected tender could not be found. Choose a valid tender from the list.',
            'standardized_drug_id.required' => 'Please select the drug or product for this tender.',
            'country_id.required' => 'Country could not be determined from the selected tender. Choose a tender with a valid country.',
            'quantity.required' => 'Please enter the required tender quantity.',
            'quantity.numeric' => 'Tender quantity must be a valid number.',
            'quantity.gt' => 'Tender quantity must be greater than zero.',
            'quantity_unit.required' => 'Please specify the quantity unit (e.g. units, tablets).',
            'discount_percentage.required' => 'Please enter the bid discount percentage.',
            'discount_percentage.numeric' => 'Bid discount percentage must be a valid number.',
            'discount_percentage.min' => 'Bid discount percentage cannot be less than 0%.',
            'discount_percentage.max' => 'Bid discount percentage cannot exceed 100%.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $tenderId = $this->input('tender_id');
            $countryId = $this->input('country_id');

            if (! filled($tenderId) || ! filled($countryId)) {
                return;
            }

            $tenderCountryId = Tender::query()->whereKey((int) $tenderId)->value('country_id');

            if ($tenderCountryId !== null && (int) $countryId !== (int) $tenderCountryId) {
                $validator->errors()->add(
                    'country_id',
                    'Country must match the selected tender. The tender defines the market geography.',
                );
            }
        });
    }
}
