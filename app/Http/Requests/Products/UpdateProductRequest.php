<?php

declare(strict_types=1);

namespace App\Http\Requests\Products;

use App\Enums\ProductType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
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
        $product = $this->route('product');
        return [
            'category_id' => ['sometimes', 'nullable', 'integer', 'exists:product_categories,id'],
            'sku' => ['sometimes', 'required', 'string', 'max:80', Rule::unique('products', 'sku')->ignore($product?->id)],
            'barcode' => ['sometimes', 'nullable', 'string', 'max:120', Rule::unique('products', 'barcode')->ignore($product?->id)],
            'name' => ['sometimes', 'required', 'string', 'max:180'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:200', Rule::unique('products', 'slug')->ignore($product?->id)],
            'product_type' => ['sometimes', 'required', Rule::enum(ProductType::class)],
            'brand' => ['sometimes', 'nullable', 'string', 'max:120'],
            'unit_of_measure' => ['sometimes', 'required', 'string', 'max:40'],
            'currency' => ['sometimes', 'required', 'string', 'size:3', 'uppercase'],
            'sale_price' => ['sometimes', 'required', 'numeric', 'min:0', 'max:9999999999999.99'],
            'cost_price' => ['sometimes', 'required', 'numeric', 'min:0', 'max:9999999999999.99'],
            'tax_rate' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'short_description' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'description' => ['sometimes', 'nullable', 'string', 'max:20000'],
            'image_url' => ['sometimes', 'nullable', 'url:http,https', 'max:2048'],
            'website_url' => ['sometimes', 'nullable', 'url:http,https', 'max:2048'],
            'weight' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'weight_unit' => ['sometimes', 'string', 'max:12'],
            'dimensions' => ['sometimes', 'nullable', 'array'],
            'dimensions.length' => ['nullable', 'numeric', 'min:0'],
            'dimensions.width' => ['nullable', 'numeric', 'min:0'],
            'dimensions.height' => ['nullable', 'numeric', 'min:0'],
            'dimensions.unit' => ['nullable', 'string', 'max:12'],
            'tags' => ['sometimes', 'nullable', 'array', 'max:30'],
            'tags.*' => ['string', 'max:40'],
            'track_inventory' => ['sometimes', 'boolean'],
            'quantity_on_hand' => ['sometimes', 'numeric', 'min:0'],
            'reorder_level' => ['sometimes', 'numeric', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
