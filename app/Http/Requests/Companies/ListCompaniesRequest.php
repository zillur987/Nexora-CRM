<?php

declare(strict_types=1);

namespace App\Http\Requests\Companies;

use App\Enums\CompanySize;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListCompaniesRequest extends FormRequest
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
            'company_type_id' => ['sometimes', 'integer'],
            'industry_id' => ['sometimes', 'integer'],
            'size' => ['sometimes', Rule::enum(CompanySize::class)],
            'country' => ['sometimes', 'string', 'max:100'],
            // a user id or the literal "unassigned"
            'owner_id' => ['sometimes', 'string', 'regex:/^(\d+|unassigned)$/'],
            'sort_by' => ['sometimes', Rule::in(['created_at', 'name', 'annual_revenue', 'billing_country'])],
            'sort_dir' => ['sometimes', Rule::in(['asc', 'desc'])],
        ];
    }
}
