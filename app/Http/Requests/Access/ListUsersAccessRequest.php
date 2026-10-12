<?php

declare(strict_types=1);

namespace App\Http\Requests\Access;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListUsersAccessRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->can('users.view') ?? false; }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:120'],
            'role_id' => ['nullable', 'integer', 'exists:roles,id'],
            'no_role' => ['nullable', 'in:0,1'],
            'sort' => ['nullable', Rule::in(['name', 'email', 'created_at'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
