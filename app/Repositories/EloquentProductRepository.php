<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\Repositories\ProductRepository;
use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class EloquentProductRepository implements ProductRepository
{
    public function paginate(array $filters): LengthAwarePaginator
    {
        $query = Product::query()->with('category:id,name,slug');

        $query->search($filters['search'] ?? null)
            ->when(isset($filters['category_id']) && $filters['category_id'] !== '', fn ($q) => $q->where('category_id', $filters['category_id']))
            ->when(isset($filters['product_type']) && $filters['product_type'] !== '', fn ($q) => $q->where('product_type', $filters['product_type']))
            ->when(isset($filters['brand']) && $filters['brand'] !== '', fn ($q) => $q->where('brand', $filters['brand']))
            ->when(isset($filters['is_active']) && $filters['is_active'] !== '', fn ($q) => $q->where('is_active', (bool) $filters['is_active']))
            ->when(isset($filters['track_inventory']) && $filters['track_inventory'] !== '', fn ($q) => $q->where('track_inventory', (bool) $filters['track_inventory']))
            ->when(($filters['low_stock'] ?? false) === true || ($filters['low_stock'] ?? null) === '1', fn ($q) => $q->lowStock());

        $allowedSorts = ['name', 'sku', 'sale_price', 'cost_price', 'created_at', 'quantity_on_hand'];
        $sort = in_array($filters['sort'] ?? 'created_at', $allowedSorts, true) ? ($filters['sort'] ?? 'created_at') : 'created_at';
        $direction = ($filters['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        return $query->orderBy($sort, $direction)
            ->paginate(min(max((int) ($filters['per_page'] ?? 20), 1), 100))
            ->withQueryString();
    }

    public function create(array $data): Product
    {
        return Product::query()->create($data)->load('category');
    }

    public function update(Product $product, array $data): Product
    {
        $product->fill($data)->save();
        return $product->fresh('category');
    }

    public function delete(Product $product): void
    {
        $product->delete();
    }
}
