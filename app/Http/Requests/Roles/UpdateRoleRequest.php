<?php

declare(strict_types=1);

namespace App\Http\Requests\Roles;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRoleRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->can('roles.edit') ?? false; }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('name'))) $this->merge(['name' => trim($this->input('name'))]);
    }

    public function rules(): array
    {
        $role = $this->route('role');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:80', Rule::unique('roles', 'name')->ignore($role?->id)],
            'description' => ['sometimes', 'nullable', 'string', 'max:255'],
            'permission_ids' => ['sometimes', 'array'],
            'permission_ids.*' => ['integer', 'distinct', 'exists:permissions,id'],
        ];
    }
}
