<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PipelineResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'is_default' => (bool) $this->is_default,
            'is_active' => (bool) $this->is_active,
            'sort_order' => (int) $this->sort_order,
            'stages_count' => (int) ($this->stages_count ?? $this->stages->count()),
            'deals_count' => (int) ($this->deals_count ?? 0),
            'stages' => $this->whenLoaded('stages', fn () => $this->stages->map(fn ($stage) => [
                'id' => $stage->id,
                'name' => $stage->name,
                'probability' => (int) $stage->probability,
                'outcome' => $stage->outcome,
                'sort_order' => (int) $stage->sort_order,
                'is_active' => (bool) ($stage->is_active ?? true),
            ])->values()),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
