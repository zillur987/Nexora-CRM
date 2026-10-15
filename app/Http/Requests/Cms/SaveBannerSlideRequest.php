<?php

declare(strict_types=1);

namespace App\Http\Requests\Cms;

use App\Rules\SafeUrl;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveBannerSlideRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can($this->isMethod('post') ? 'banners.create' : 'banners.edit') ?? false;
    }

    /** Blank rows from the form are dropped before validation. */
    protected function prepareForValidation(): void
    {
        $lines = collect($this->input('title_lines', []))->map(fn ($l) => is_string($l) ? trim($l) : '')->filter()->values()->all();
        $facts = collect($this->input('facts', []))
            ->filter(fn ($f) => is_array($f) && (filled($f['value'] ?? null) || filled($f['label'] ?? null)))
            ->values()->all();

        $this->merge(['title_lines' => $lines, 'facts' => $facts]);
    }

    public function rules(): array
    {
        return [
            'location_label' => ['nullable', 'string', 'max:120'],
            'facts' => ['nullable', 'array', 'max:4'],
            'cta_label' => ['nullable', 'string', 'max:80', 'required_with:cta_url'],
            'cta_url' => ['nullable', 'string', 'max:2048', 'required_with:cta_label', new SafeUrl()],
            // 1920x1080 recommended; the minimum keeps the full-bleed slide sharp.
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:min_width=1280,min_height=640'],
            'remove_image' => ['sometimes', 'boolean'],
            'gradient' => ['sometimes', Rule::in(['a', 'b', 'c', 'd', 'e', 'f'])],
            'is_active' => ['sometimes', 'boolean'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ];
    }
}
