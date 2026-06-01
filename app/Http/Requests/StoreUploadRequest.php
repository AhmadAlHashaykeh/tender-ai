<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $extensions = implode(',', config('import.allowed_extensions', ['xlsx', 'xls', 'csv']));
        $maxKb = (int) config('import.max_upload_size_kb', 51200);

        return [
            'file' => ['required', 'file', 'mimes:'.$extensions, 'max:'.$maxKb],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'Please select an Excel or CSV file to upload.',
            'file.mimes' => 'Only .xlsx, .xls, and .csv files are supported.',
        ];
    }
}
