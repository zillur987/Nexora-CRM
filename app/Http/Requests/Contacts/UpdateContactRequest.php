<?php

declare(strict_types=1);

namespace App\Http\Requests\Contacts;

use App\Http\Requests\Contacts\Concerns\NormalizesContactInput;
use App\Http\Requests\Contacts\Rules\ContactRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateContactRequest extends FormRequest
{
    use NormalizesContactInput;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = ContactRules::forUpdate();

        $rules['email'][] = Rule::unique('contacts', 'email')
            ->ignore($this->route('contact'))
            ->whereNull('deleted_at');

        return $rules;
    }
}
