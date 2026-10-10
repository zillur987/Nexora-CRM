<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Deal */
class DealResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,

            'owner_id' => $this->owner_id,
            'owner' => $this->whenLoaded('owner', fn () => $this->owner?->only(['id', 'name'])),

            'company_id' => $this->company_id,
            'company' => $this->whenLoaded('company', fn () => $this->company?->only(['id', 'name'])),
            'contact_id' => $this->contact_id,
            'contact' => $this->whenLoaded('contact', fn () => $this->contact
                ? ['id' => $this->contact->id, 'name' => $this->contact->full_name]
                : null),

            'amount' => $this->amount, // decimal string, e.g. "1250.00"
            'currency' => $this->currency,
            'probability' => $this->probability,
            'weighted_amount' => $this->weighted_amount,

            'expected_close_date' => $this->expected_close_date?->toDateString(),
            'actual_close_date' => $this->actual_close_date?->toDateString(),
            'is_overdue' => $this->whenLoaded('stage', fn () => $this->isOverdue()),

            'deal_stage_id' => $this->deal_stage_id,
            'stage' => $this->whenLoaded('stage', fn () => $this->stage ? [
                'id' => $this->stage->id,
                'name' => $this->stage->name,
                'probability' => $this->stage->probability,
                'outcome' => $this->stage->outcome->value,
            ] : null),
            'deal_type_id' => $this->deal_type_id,
            'type' => $this->whenLoaded('type', fn () => $this->type?->only(['id', 'name'])),
            'lead_source_id' => $this->lead_source_id,
            'lead_source' => $this->whenLoaded('leadSource', fn () => $this->leadSource?->only(['id', 'name'])),

            'priority' => $this->priority?->value,
            'next_step' => $this->next_step,
            'lost_reason' => $this->lost_reason,

            'description' => $this->description,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
