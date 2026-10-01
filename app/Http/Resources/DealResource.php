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
            'title' => $this->title,
            'amount' => $this->amount, // decimal string, e.g. "1500.00"
            'currency' => $this->currency,
            'stage' => $this->stage->value,
            'is_closed' => $this->stage->isClosed(),
            'allowed_transitions' => array_map(
                fn ($stage) => $stage->value,
                $this->stage->allowedTransitions(),
            ),
            'expected_close_date' => $this->expected_close_date?->toDateString(),
            'closed_at' => $this->closed_at?->toIso8601String(),
            'contact_id' => $this->contact_id,
            'contact' => ContactResource::make($this->whenLoaded('contact')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
