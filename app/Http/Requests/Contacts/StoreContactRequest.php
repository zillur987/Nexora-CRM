<?php

declare(strict_types=1);

namespace App\Http\Requests\Contacts;

use App\Enums\ContactStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower(trim($this->input('email')))]);
        }
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:80'],
            'last_name' => ['required', 'string', 'max:80'],
            'email' => ['required', 'email:rfc', 'max:254', Rule::unique('contacts', 'email')],
            'phone' => ['nullable', 'string', 'min:5', 'max:30'],
            'company' => ['nullable', 'string', 'max:120'],
            'status' => ['sometimes', Rule::enum(ContactStatus::class)],
        ];
    }
}
