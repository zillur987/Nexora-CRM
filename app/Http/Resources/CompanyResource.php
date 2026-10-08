<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Company */
class CompanyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,

            'owner_id' => $this->owner_id,
            'owner' => $this->whenLoaded('owner', fn () => $this->owner?->only(['id', 'name'])),
            'parent_id' => $this->parent_id,
            'parent' => $this->whenLoaded('parent', fn () => $this->parent?->only(['id', 'name'])),
            'children' => $this->whenLoaded('children', fn () => $this->children->map->only(['id', 'name'])->values()),
            'children_count' => $this->whenCounted('children'),

            'industry_id' => $this->industry_id,
            'industry' => $this->whenLoaded('industry', fn () => $this->industry?->only(['id', 'name'])),
            'company_type_id' => $this->company_type_id,
            'type' => $this->whenLoaded('type', fn () => $this->type?->only(['id', 'name'])),

            'size' => $this->size?->value,
            'size_label' => $this->size?->label(),
            'annual_revenue' => $this->annual_revenue, // decimal string or null
            'currency' => $this->currency,

            'phone' => $this->phone,
            'email' => $this->email,
            'website' => $this->website,
            'linkedin' => $this->linkedin,
            'twitter' => $this->twitter,
            'instagram' => $this->instagram,
            'facebook' => $this->facebook,

            'billing_street' => $this->billing_street,
            'billing_city' => $this->billing_city,
            'billing_zip' => $this->billing_zip,
            'billing_state' => $this->billing_state,
            'billing_country' => $this->billing_country,
            'billing_address' => $this->billing_address,

            'shipping_street' => $this->shipping_street,
            'shipping_city' => $this->shipping_city,
            'shipping_zip' => $this->shipping_zip,
            'shipping_state' => $this->shipping_state,
            'shipping_country' => $this->shipping_country,
            'shipping_address' => $this->shipping_address,

            'description' => $this->description,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
