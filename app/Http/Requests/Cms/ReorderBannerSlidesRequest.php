<?php

declare(strict_types=1);

namespace App\Http\Requests\Cms;

use Illuminate\Foundation\Http\FormRequest;

class ReorderBannerSlidesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('banners.edit') ?? false;
    }

    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'distinct'],
        ];
    }
}
