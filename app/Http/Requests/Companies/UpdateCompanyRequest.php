<?php

declare(strict_types=1);

namespace App\Http\Requests\Companies;

use App\Http\Requests\Companies\Concerns\NormalizesCompanyInput;
use App\Http\Requests\Companies\Rules\CompanyRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCompanyRequest extends FormRequest
{
    use NormalizesCompanyInput;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = CompanyRules::forUpdate();

        $rules['name'][] = Rule::unique('companies', 'name')
            ->ignore($this->route('company'))
            ->whereNull('deleted_at');

        return $rules;
    }
}
