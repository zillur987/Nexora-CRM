<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Quote */
class QuoteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'quote_number' => $this->quote_number,
            'subject' => $this->subject,
            'status' => $this->status->value,
            'is_expired' => $this->isExpired(),

            'owner_id' => $this->owner_id,
            'owner' => $this->whenLoaded('owner', fn () => $this->owner?->only(['id', 'name'])),
            'deal_id' => $this->deal_id,
            'deal' => $this->whenLoaded('deal', fn () => $this->deal?->only(['id', 'name'])),
            'company_id' => $this->company_id,
            'company' => $this->whenLoaded('company', fn () => $this->company?->only(['id', 'name'])),
            'contact_id' => $this->contact_id,
            'contact' => $this->whenLoaded('contact', fn () => $this->contact
                ? ['id' => $this->contact->id, 'name' => $this->contact->full_name]
                : null),

            'issue_date' => $this->issue_date?->toDateString(),
            'valid_until' => $this->valid_until?->toDateString(),
            'currency' => $this->currency,

            // decimal strings, e.g. "1250.00"
            'subtotal' => $this->subtotal,
            'discount_total' => $this->discount_total,
            'tax_total' => $this->tax_total,
            'total' => $this->total,

            'terms' => $this->terms,
            'notes' => $this->notes,

            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'name' => $item->name,
                'description' => $item->description,
                'unit' => $item->unit,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'discount_percent' => $item->discount_percent,
                'tax_rate' => $item->tax_rate,
                'line_total' => $item->line_total,
            ])->values()),

            'sent_at' => $this->sent_at?->toIso8601String(),
            'accepted_at' => $this->accepted_at?->toIso8601String(),
            'rejected_at' => $this->rejected_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
