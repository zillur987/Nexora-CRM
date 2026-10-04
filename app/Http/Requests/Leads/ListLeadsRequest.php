<?php

declare(strict_types=1);

namespace App\Http\Requests\Leads;

use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListLeadsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'search' => ['sometimes', 'string', 'max:100'],
            'status' => ['sometimes', Rule::enum(LeadStatus::class)],
            'source' => ['sometimes', Rule::enum(LeadSource::class)],
            // a user id or the literal "unassigned"
            'assigned_to' => ['sometimes', 'string', 'regex:/^(\d+|unassigned)$/'],
            'sort_by' => ['sometimes', Rule::in(['created_at', 'last_name', 'company', 'score', 'estimated_value', 'last_contacted_at'])],
            'sort_dir' => ['sometimes', Rule::in(['asc', 'desc'])],
        ];
    }
}
