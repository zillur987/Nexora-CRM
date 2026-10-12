<?php

declare(strict_types=1);

namespace App\Http\Requests\Roles;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListRolesRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->can('roles.view') ?? false; }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:120'],
            'is_system' => ['nullable', 'in:0,1'],
            'sort' => ['nullable', Rule::in(['name', 'created_at', 'users_count', 'permissions_count'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
