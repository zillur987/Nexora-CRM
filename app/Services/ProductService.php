<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\Repositories\ProductRepository;
use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class ProductService
{
    public function __construct(private readonly ProductRepository $products) {}

    public function list(array $filters): LengthAwarePaginator { return $this->products->paginate($filters); }

    public function create(array $data): Product
    {
        $data = $this->normalize($data);
        return $this->products->create($data);
    }

    public function update(Product $product, array $data): Product
    {
        $data = $this->normalize($data, $product);
        return $this->products->update($product, $data);
    }

    public function delete(Product $product): void { $this->products->delete($product); }

    private function normalize(array $data, ?Product $product = null): array
    {
        if (isset($data['name'])) {
            $data['name'] = trim($data['name']);
            if (! array_key_exists('slug', $data) || blank($data['slug'])) {
                $base = Str::slug($data['name']);
                $slug = $base;
                $suffix = 2;
                while (Product::query()->where('slug', $slug)->when($product, fn ($q) => $q->whereKeyNot($product->id))->exists()) {
                    $slug = $base.'-'.$suffix++;
                }
                $data['slug'] = $slug;
            } else {
                $data['slug'] = Str::slug($data['slug']);
            }
        }

        if (isset($data['sku'])) $data['sku'] = strtoupper(trim($data['sku']));
        if (isset($data['barcode']) && $data['barcode'] === '') $data['barcode'] = null;
        if (isset($data['brand']) && $data['brand'] === '') $data['brand'] = null;

        if (isset($data['sale_price'], $data['cost_price']) && (float) $data['sale_price'] < 0) {
            throw ValidationException::withMessages(['sale_price' => 'Sale price cannot be negative.']);
        }

        return $data;
    }
}
