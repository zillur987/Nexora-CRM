<?php

declare(strict_types=1);

namespace App\Http\Requests\Pipelines;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePipelineStageRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    protected function prepareForValidation(): void { if (is_string($this->name)) $this->merge(['name' => trim($this->name)]); }
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'probability' => ['required', 'integer', 'between:0,100'],
            'outcome' => ['required', Rule::in(['open', 'won', 'lost'])],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:65535'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
