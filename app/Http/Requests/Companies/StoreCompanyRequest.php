<?php

declare(strict_types=1);

namespace App\Http\Requests\Companies;

use App\Http\Requests\Companies\Concerns\NormalizesCompanyInput;
use App\Http\Requests\Companies\Rules\CompanyRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCompanyRequest extends FormRequest
{
    use NormalizesCompanyInput;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = CompanyRules::attributes();

        // Uniqueness among live rows only (soft deletes make a DB-level unique awkward).
        $rules['name'][] = Rule::unique('companies', 'name')->whereNull('deleted_at');

        return $rules;
    }
}
