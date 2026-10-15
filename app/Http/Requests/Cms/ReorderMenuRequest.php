<?php

declare(strict_types=1);

namespace App\Http\Requests\Cms;

use Illuminate\Foundation\Http\FormRequest;

class ReorderMenuRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('menus.edit') ?? false;
    }

    public function rules(): array
    {
        return [
            'parent_id' => ['nullable', 'integer', 'exists:menu_items,id'],
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'distinct'],
        ];
    }
}
