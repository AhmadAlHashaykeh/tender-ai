<?php

namespace App\Http\Requests;

use App\Services\Tender\TenderGroupService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StorePredictionRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $merge = [
            'quantity_unit' => filled($this->input('quantity_unit')) ? $this->input('quantity_unit') : 'units',
        ];

        $groupKey = $this->input('tender_group_key');
        if (filled($groupKey)) {
            $merge['tender_group_key'] = strtoupper((string) $groupKey);

            $groupService = app(TenderGroupService::class);
            $countryId = $groupService->resolveCountryId($merge['tender_group_key']);
            if ($countryId !== null) {
                $merge['country_id'] = $countryId;
            }

            if (! filled($this->input('tender_id'))) {
                $representativeTenderId = $groupService->resolveRepresentativeTenderId($merge['tender_group_key']);
                if ($representativeTenderId !== null) {
                    $merge['tender_id'] = $representativeTenderId;
                }
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
            'tender_group_key' => ['required', 'string', 'max:120'],
            'tender_id' => ['nullable', 'integer', 'exists:tenders,id'],
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
            'tender_group_key.required' => 'Please select the tender program this recommendation is for.',
            'standardized_drug_id.required' => 'Please select the drug or product for this tender program.',
            'country_id.required' => 'Country could not be determined from the selected tender program. Choose a program with a valid market.',
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
            $groupKey = $this->input('tender_group_key');
            $drugId = $this->input('standardized_drug_id');

            if (! filled($groupKey)) {
                return;
            }

            $groupService = app(TenderGroupService::class);

            if (! $groupService->groupExists((string) $groupKey)) {
                $validator->errors()->add(
                    'tender_group_key',
                    'The selected tender program could not be found. Choose a valid program from the list.',
                );

                return;
            }

            if (filled($drugId) && ! $groupService->isDrugInGroup((string) $groupKey, (int) $drugId)) {
                $validator->errors()->add(
                    'standardized_drug_id',
                    'The selected product is not available in the chosen tender program.',
                );
            }
        });
    }
}
