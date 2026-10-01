<?php

declare(strict_types=1);

namespace App\Http\Requests\Deals;

use Illuminate\Foundation\Http\FormRequest;

class StoreDealRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('currency'))) {
            $this->merge(['currency' => strtoupper(trim($this->input('currency')))]);
        }
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:160'],
            'amount' => ['required', 'numeric', 'min:0', 'max:999999999999.99'],
            'currency' => ['sometimes', 'string', 'size:3'],
            // The contact must exist and not be soft-deleted.
            'contact_id' => ['required', 'uuid', 'exists:contacts,id,deleted_at,NULL'],
            'expected_close_date' => ['nullable', 'date'],
        ];
    }
}
