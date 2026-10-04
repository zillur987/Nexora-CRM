<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Lead */
class LeadResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->full_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'company' => $this->company,
            'job_title' => $this->job_title,
            'source' => $this->source->value,
            'status' => $this->status->value,
            'score' => $this->score,
            'estimated_value' => $this->estimated_value, // decimal string or null
            'currency' => $this->currency,
            'notes' => $this->notes,
            'is_converted' => $this->status->isConverted(),
            'can_convert' => $this->status->canConvert(),
            'allowed_transitions' => array_map(
                fn ($status) => $status->value,
                $this->status->allowedTransitions(),
            ),
            'assigned_to' => $this->assigned_to,
            'owner' => $this->whenLoaded('owner', fn () => [
                'id' => $this->owner->id,
                'name' => $this->owner->name,
            ]),
            'converted_contact_id' => $this->converted_contact_id,
            'last_contacted_at' => $this->last_contacted_at?->toIso8601String(),
            'converted_at' => $this->converted_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
