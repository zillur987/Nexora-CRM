<?php

declare(strict_types=1);

namespace App\Http\Requests\Leads;

use App\Enums\LeadSource;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Status is intentionally absent: it only changes via the status / convert endpoints. */
class UpdateLeadRequest extends FormRequest
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
            'first_name' => ['sometimes', 'required', 'string', 'max:80'],
            'last_name' => ['sometimes', 'required', 'string', 'max:80'],
            'email' => [
                'sometimes', 'required', 'email:rfc', 'max:254',
                Rule::unique('leads', 'email')->ignore($this->route('lead'))->whereNull('deleted_at'),
            ],
            'phone' => ['sometimes', 'nullable', 'string', 'min:5', 'max:30'],
            'company' => ['sometimes', 'nullable', 'string', 'max:120'],
            'job_title' => ['sometimes', 'nullable', 'string', 'max:120'],
            'source' => ['sometimes', Rule::enum(LeadSource::class)],
            'score' => ['sometimes', 'integer', 'between:0,100'],
            'estimated_value' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'assigned_to' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
        ];
    }
}
