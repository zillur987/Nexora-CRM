<?php

declare(strict_types=1);

namespace App\Http\Requests\Cms;

use App\Enums\MenuItemType;
use App\Rules\SafeUrl;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Used for both create (POST) and update (PUT/PATCH, or POST + _method for the image upload). */
class SaveMenuItemRequest extends FormRequest
{
    private function isStore(): bool
    {
        return $this->isMethod('post');
    }

    public function authorize(): bool
    {
        return $this->user()?->can($this->isStore() ? 'menus.create' : 'menus.edit') ?? false;
    }

    public function rules(): array
    {
        $type = $this->isStore() ? $this->input('type') : $this->route('menuItem')?->type?->value;

        $rules = [
            'label' => ['required', 'string', 'max:120'],
            'is_active' => ['sometimes', 'boolean'],
        ];

        if ($this->isStore()) {
            $rules['type'] = ['required', Rule::enum(MenuItemType::class)];
            $rules['parent_id'] = ['nullable', 'integer', 'exists:menu_items,id'];
        }

        if (in_array($type, ['link', 'mega', 'item'], true)) {
            $rules['url'] = [$type === 'mega' ? 'nullable' : 'required', 'string', 'max:2048', new SafeUrl()];
            $rules['target'] = ['sometimes', Rule::in(['_self', '_blank'])];
        }

        if ($type === 'item') {
            $rules['description'] = ['nullable', 'string', 'max:160'];
        }

        if ($type === 'mega') {
            $rules += [
                'meta_text' => ['nullable', 'string', 'max:160'],
                'feature_label' => ['nullable', 'string', 'max:80'],
                'feature_title' => ['nullable', 'string', 'max:120'],
                'feature_sub' => ['nullable', 'string', 'max:160'],
                'feature_url' => ['nullable', 'string', 'max:2048', new SafeUrl()],
                'feature_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
                'remove_feature_image' => ['sometimes', 'boolean'],
            ];
        }

        return $rules;
    }
}
