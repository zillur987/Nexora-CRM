<?php

declare(strict_types=1);

namespace App\Http\Requests\Products;

use App\Enums\ProductType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    protected function prepareForValidation(): void
    {
        foreach (['name', 'sku', 'barcode', 'brand'] as $field) {
            if (is_string($this->input($field))) $this->merge([$field => trim($this->input($field))]);
        }
    }

    public function rules(): array
    {
        return [
            'category_id' => ['nullable', 'integer', 'exists:product_categories,id'],
            'sku' => ['required', 'string', 'max:80', 'unique:products,sku'],
            'barcode' => ['nullable', 'string', 'max:120', 'unique:products,barcode'],
            'name' => ['required', 'string', 'max:180'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:200', 'unique:products,slug'],
            'product_type' => ['required', Rule::enum(ProductType::class)],
            'brand' => ['nullable', 'string', 'max:120'],
            'unit_of_measure' => ['required', 'string', 'max:40'],
            'currency' => ['required', 'string', 'size:3', 'uppercase'],
            'sale_price' => ['required', 'numeric', 'min:0', 'max:9999999999999.99'],
            'cost_price' => ['required', 'numeric', 'min:0', 'max:9999999999999.99'],
            'tax_rate' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'short_description' => ['nullable', 'string', 'max:1000'],
            'description' => ['nullable', 'string', 'max:20000'],
            'image_url' => ['nullable', 'url:http,https', 'max:2048'],
            'website_url' => ['nullable', 'url:http,https', 'max:2048'],
            'weight' => ['nullable', 'numeric', 'min:0'],
            'weight_unit' => ['sometimes', 'string', 'max:12'],
            'dimensions' => ['nullable', 'array'],
            'dimensions.length' => ['nullable', 'numeric', 'min:0'],
            'dimensions.width' => ['nullable', 'numeric', 'min:0'],
            'dimensions.height' => ['nullable', 'numeric', 'min:0'],
            'dimensions.unit' => ['nullable', 'string', 'max:12'],
            'tags' => ['nullable', 'array', 'max:30'],
            'tags.*' => ['string', 'max:40'],
            'track_inventory' => ['sometimes', 'boolean'],
            'quantity_on_hand' => ['sometimes', 'numeric', 'min:0'],
            'reorder_level' => ['sometimes', 'numeric', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
