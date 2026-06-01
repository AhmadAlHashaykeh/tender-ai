<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreManualImportRequest extends FormRequest
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
            'code' => ['nullable', 'string', 'max:255'],
            'inn' => ['nullable', 'string', 'max:2000'],
            'product_name' => ['nullable', 'string', 'max:2000'],
            'country' => ['required', 'string', 'max:255'],
            'tender_number' => ['nullable', 'string', 'max:255'],
            'awarded_price' => ['nullable', 'numeric', 'min:0'],
            'price_usd' => ['required', 'numeric', 'gt:0'],
            'winner' => ['nullable', 'string', 'max:255'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'version' => ['nullable', 'string', 'max:64'],
            'year' => ['required', 'integer', 'min:1900', 'max:2100'],
            'qty' => ['nullable', 'numeric', 'min:0'],
            'tender_value' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $hasDrug = filled($this->input('code'))
                || filled($this->input('inn'))
                || filled($this->input('product_name'));

            if (! $hasDrug) {
                $validator->errors()->add(
                    'code',
                    'At least one of Code, INN, or Product Name is required.'
                );
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'product_name' => 'Product Name',
            'tender_number' => 'Tender #',
            'awarded_price' => 'Awarded price',
            'price_usd' => 'Price USD',
            'company_name' => 'Company Name',
            'tender_value' => 'Tender Value',
        ];
    }
}
