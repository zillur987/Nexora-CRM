<?php

declare(strict_types=1);

namespace App\Http\Requests\Companies;

use Illuminate\Foundation\Http\FormRequest;

class StoreCompanyLookupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('name'))) {
            $this->merge(['name' => preg_replace('/\s+/', ' ', trim($this->input('name')))]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
        ];
    }
}
