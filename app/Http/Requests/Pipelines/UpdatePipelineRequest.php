<?php

declare(strict_types=1);

namespace App\Http\Requests\Pipelines;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePipelineRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    protected function prepareForValidation(): void
    {
        if (is_string($this->name)) $this->merge(['name' => trim($this->name)]);
    }

    public function rules(): array
    {
        $pipeline = $this->route('pipeline');
        return [
            'name' => ['sometimes', 'required', 'string', 'max:120', Rule::unique('pipelines', 'name')->ignore($pipeline?->id)->whereNull('deleted_at')],
            'description' => ['sometimes', 'nullable', 'string', 'max:3000'],
            'is_default' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:65535'],
        ];
    }
}
