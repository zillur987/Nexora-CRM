<?php

declare(strict_types=1);

namespace App\Http\Requests\Tasks;

use Illuminate\Foundation\Http\FormRequest;

class StoreTaskOptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        return $user && ($user->can('tasks.create') || $user->can('tasks.edit'));
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('name'))) $this->merge(['name' => trim($this->input('name'))]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:80'],
            'color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ];
    }
}
