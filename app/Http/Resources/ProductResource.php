<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'category_id' => $this->category_id,
            'category' => $this->whenLoaded('category', fn () => $this->category ? [
                'id' => $this->category->id, 'name' => $this->category->name, 'slug' => $this->category->slug,
            ] : null),
            'sku' => $this->sku,
            'barcode' => $this->barcode,
            'name' => $this->name,
            'slug' => $this->slug,
            'product_type' => $this->product_type,
            'brand' => $this->brand,
            'unit_of_measure' => $this->unit_of_measure,
            'currency' => $this->currency,
            'sale_price' => $this->sale_price,
            'cost_price' => $this->cost_price,
            'tax_rate' => $this->tax_rate,
            'short_description' => $this->short_description,
            'description' => $this->description,
            'image_url' => $this->image_url,
            'website_url' => $this->website_url,
            'weight' => $this->weight,
            'weight_unit' => $this->weight_unit,
            'dimensions' => $this->dimensions,
            'tags' => $this->tags ?? [],
            'track_inventory' => (bool) $this->track_inventory,
            'quantity_on_hand' => $this->quantity_on_hand,
            'reorder_level' => $this->reorder_level,
            'is_low_stock' => (bool) ($this->track_inventory && $this->quantity_on_hand <= $this->reorder_level),
            'is_active' => (bool) $this->is_active,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
