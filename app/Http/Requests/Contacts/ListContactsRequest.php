<?php

declare(strict_types=1);

namespace App\Http\Requests\Contacts;

use App\Enums\ContactStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListContactsRequest extends FormRequest
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
            'status' => ['sometimes', Rule::enum(ContactStatus::class)],
            'sort_by' => ['sometimes', Rule::in(['created_at', 'last_name', 'company'])],
            'sort_dir' => ['sometimes', Rule::in(['asc', 'desc'])],
        ];
    }
}
