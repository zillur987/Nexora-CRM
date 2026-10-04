<?php

declare(strict_types=1);

namespace App\Http\Requests\Leads;

use App\Enums\LeadSource;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $merge = [];

        if (is_string($this->input('email'))) {
            $merge['email'] = mb_strtolower(trim($this->input('email')));
        }
        if (is_string($this->input('currency'))) {
            $merge['currency'] = strtoupper(trim($this->input('currency')));
        }

        $this->merge($merge);
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:80'],
            'last_name' => ['required', 'string', 'max:80'],
            'email' => ['required', 'email:rfc', 'max:254', Rule::unique('leads', 'email')->whereNull('deleted_at')],
            'phone' => ['nullable', 'string', 'min:5', 'max:30'],
            'company' => ['nullable', 'string', 'max:120'],
            'job_title' => ['nullable', 'string', 'max:120'],
            'source' => ['sometimes', Rule::enum(LeadSource::class)],
            'score' => ['sometimes', 'integer', 'between:0,100'],
            'estimated_value' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }
}
