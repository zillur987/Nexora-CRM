<?php

declare(strict_types=1);

namespace App\Http\Requests\Products;

use App\Enums\ProductType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListProductsRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:120'],
            'category_id' => ['nullable', 'integer', 'exists:product_categories,id'],
            'product_type' => ['nullable', Rule::enum(ProductType::class)],
            'brand' => ['nullable', 'string', 'max:120'],
            'is_active' => ['nullable', 'in:0,1'],
            'track_inventory' => ['nullable', 'in:0,1'],
            'low_stock' => ['nullable', 'in:0,1'],
            'sort' => ['nullable', Rule::in(['name', 'sku', 'sale_price', 'cost_price', 'created_at', 'quantity_on_hand'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
