<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreUpcomingTenderRequest extends FormRequest
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
            'tender_name' => ['required', 'string', 'max:255'],
            'tender_number' => ['required', 'string', 'max:255'],
            'country' => ['required', 'string', 'max:255'],
            'year' => ['required', 'integer', 'min:1900', 'max:2100'],
            'version' => ['nullable', 'string', 'max:64'],
            'expected_product_name' => ['nullable', 'string', 'max:2000'],
            'expected_inn' => ['nullable', 'string', 'max:2000'],
            'expected_code' => ['nullable', 'string', 'max:255'],
            'expected_qty' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'expected_closing_date' => ['nullable', 'date'],
            'authority' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $hasDrug = filled($this->input('expected_code'))
                || filled($this->input('expected_inn'))
                || filled($this->input('expected_product_name'));

            if (! $hasDrug) {
                $validator->errors()->add(
                    'expected_code',
                    'At least one of Expected Code, Expected INN, or Expected Product Name is required.'
                );
            }
        });
    }
}
