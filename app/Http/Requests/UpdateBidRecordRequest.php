<?php

namespace App\Http\Requests;

use App\Services\Management\BidRecordManagementService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBidRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        foreach (['is_winner', 'is_analytics_ready', 'excluded_from_stats'] as $field) {
            if ($this->has($field)) {
                $this->merge([
                    $field => filter_var($this->input($field), FILTER_VALIDATE_BOOLEAN),
                ]);
            }
        }

        foreach (['company_id', 'standardized_drug_id', 'tender_id'] as $field) {
            if ($this->has($field) && $this->input($field) === '') {
                $this->merge([$field => null]);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'price_usd' => ['nullable', 'numeric', 'min:0'],
            'original_awarded_price' => ['nullable', 'numeric', 'min:0'],
            'quantity' => ['nullable', 'numeric', 'min:0'],
            'tender_value' => ['nullable', 'numeric', 'min:0'],
            'bid_status' => ['required', Rule::in(BidRecordManagementService::BID_STATUSES)],
            'is_winner' => ['required', 'boolean'],
            'is_analytics_ready' => ['required', 'boolean'],
            'excluded_from_stats' => ['required', 'boolean'],
            'exclusion_reason' => ['nullable', 'string', 'max:500'],
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
            'standardized_drug_id' => ['nullable', 'integer', 'exists:standardized_drugs,id'],
            'tender_id' => ['nullable', 'integer', 'exists:tenders,id'],
        ];
    }
}
