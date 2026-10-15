<?php

declare(strict_types=1);

namespace App\Http\Requests\Cms;

use App\Rules\SafeUrl;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSiteSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('site_settings.edit') ?? false;
    }

    public function rules(): array
    {
        return match ($this->route('group')) {
            'header' => [
                'logo_image' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:1024'],
                'remove_logo_image' => ['sometimes', 'boolean'],
                'phone' => ['nullable', 'regex:/^\+?[0-9\s\-()]{6,20}$/'],
                'whatsapp' => ['nullable', 'regex:/^[0-9]{8,15}$/'],   // digits only, with country code (e.g. 8801XXXXXXXXX)
                'show_language_switch' => ['sometimes', 'boolean'],
                'languages' => ['nullable', 'array', 'max:5'],
                'languages.*.code' => ['required', 'alpha_dash', 'max:5'],
                'languages.*.label' => ['required', 'string', 'max:30'],
                'languages.*.short' => ['required', 'string', 'max:4'],
            ],
            'hero' => [
                'video' => ['nullable', 'file', 'mimetypes:video/mp4,video/webm', 'max:30720'],
                'remove_video' => ['sometimes', 'boolean'],
                'poster' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
                'remove_poster' => ['sometimes', 'boolean'],
                'show_search' => ['sometimes', 'boolean'],
            ],
            default => [],
        };
    }
}
