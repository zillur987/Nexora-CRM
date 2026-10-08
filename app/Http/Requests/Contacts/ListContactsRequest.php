<?php

declare(strict_types=1);

namespace App\Http\Requests\Contacts;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListContactsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** Empty filters arrive as null (ConvertEmptyStringsToNull), so every optional filter is nullable. */
    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'search' => ['sometimes', 'nullable', 'string', 'max:100'],
            'contact_stage_id' => ['sometimes', 'nullable', 'integer'],
            'contact_source_id' => ['sometimes', 'nullable', 'integer'],
            'industry_id' => ['sometimes', 'nullable', 'integer'],
            'company_id' => ['sometimes', 'nullable', 'integer'],
            'country' => ['sometimes', 'nullable', 'string', 'max:100'],
            // a user id or the literal "unassigned"
            'owner_id' => ['sometimes', 'nullable', 'string', 'regex:/^(\d+|unassigned)$/'],
            'sort_by' => ['sometimes', 'nullable', Rule::in(['created_at', 'first_name', 'last_name', 'birthday', 'present_country'])],
            'sort_dir' => ['sometimes', 'nullable', Rule::in(['asc', 'desc'])],
        ];
    }
}
