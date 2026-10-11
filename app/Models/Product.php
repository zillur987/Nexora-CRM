<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'category_id', 'sku', 'barcode', 'name', 'slug', 'product_type', 'brand',
        'unit_of_measure', 'currency', 'sale_price', 'cost_price', 'tax_rate',
        'short_description', 'description', 'image_url', 'website_url', 'weight',
        'weight_unit', 'dimensions', 'tags', 'track_inventory', 'quantity_on_hand',
        'reorder_level', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'sale_price' => 'decimal:2', 'cost_price' => 'decimal:2', 'tax_rate' => 'decimal:4',
            'weight' => 'decimal:3', 'quantity_on_hand' => 'decimal:3', 'reorder_level' => 'decimal:3',
            'dimensions' => 'array', 'tags' => 'array', 'track_inventory' => 'boolean', 'is_active' => 'boolean',
        ];
    }

    public function category(): BelongsTo { return $this->belongsTo(ProductCategory::class, 'category_id'); }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        if (! filled($search)) return $query;
        $term = '%'.addcslashes(trim($search), '%_\\\\').'%';
        return $query->where(fn (Builder $q) => $q
            ->where('name', 'like', $term)->orWhere('sku', 'like', $term)
            ->orWhere('barcode', 'like', $term)->orWhere('brand', 'like', $term));
    }

    public function scopeLowStock(Builder $query): Builder
    {
        return $query->where('track_inventory', true)->whereColumn('quantity_on_hand', '<=', 'reorder_level');
    }
}
