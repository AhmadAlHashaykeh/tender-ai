<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GlobalSearchRequest extends FormRequest
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
            'q' => ['required', 'string', 'min:2', 'max:100'],
        ];
    }

    public function sanitizedQuery(): string
    {
        $query = strip_tags((string) $this->input('q', ''));
        $query = preg_replace('/\s+/u', ' ', trim($query)) ?? '';

        return mb_substr($query, 0, 100);
    }
}
