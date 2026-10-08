<?php

declare(strict_types=1);

namespace App\Http\Requests\Contacts;

use App\Http\Requests\Contacts\Concerns\NormalizesContactInput;
use App\Http\Requests\Contacts\Rules\ContactRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreContactRequest extends FormRequest
{
    use NormalizesContactInput;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = ContactRules::attributes();

        // Uniqueness among live rows only (soft deletes make a DB-level unique awkward).
        $rules['email'][] = Rule::unique('contacts', 'email')->whereNull('deleted_at');

        return $rules;
    }
}
