<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Contact */
class ContactResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->full_name,

            'owner_id' => $this->owner_id,
            'owner' => $this->whenLoaded('owner', fn () => $this->owner?->only(['id', 'name'])),

            'email' => $this->email,
            'birthday' => $this->birthday?->toDateString(),
            'phone' => $this->phone,

            'company_id' => $this->company_id,
            'company' => $this->whenLoaded('company', fn () => $this->company?->only(['id', 'name'])),
            'job_title' => $this->job_title,
            'department' => $this->department,

            'industry_id' => $this->industry_id,
            'industry' => $this->whenLoaded('industry', fn () => $this->industry?->only(['id', 'name'])),
            'contact_source_id' => $this->contact_source_id,
            'source' => $this->whenLoaded('source', fn () => $this->source?->only(['id', 'name'])),
            'contact_stage_id' => $this->contact_stage_id,
            'stage' => $this->whenLoaded('stage', fn () => $this->stage?->only(['id', 'name'])),

            'present_address' => $this->present_address,
            'present_city' => $this->present_city,
            'present_zip' => $this->present_zip,
            'present_state' => $this->present_state,
            'present_country' => $this->present_country,
            'present_full_address' => $this->present_full_address,

            'permanent_address' => $this->permanent_address,
            'permanent_city' => $this->permanent_city,
            'permanent_zip' => $this->permanent_zip,
            'permanent_state' => $this->permanent_state,
            'permanent_country' => $this->permanent_country,
            'permanent_full_address' => $this->permanent_full_address,

            'twitter' => $this->twitter,
            'linkedin' => $this->linkedin,

            'description' => $this->description,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
