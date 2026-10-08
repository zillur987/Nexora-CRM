<?php

declare(strict_types=1);

namespace App\Http\Requests\Companies;

use Illuminate\Foundation\Http\FormRequest;

class CompanyOptionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // The company being edited, so it can't be picked as its own parent.
            'exclude' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
