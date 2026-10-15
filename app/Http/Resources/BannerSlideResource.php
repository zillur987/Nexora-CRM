<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Services\Cms\MediaStorage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BannerSlideResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $now = now();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'location_label' => $this->location_label,
            'title_lines' => $this->title_lines ?? [],
            'text' => $this->text,
            'facts' => $this->facts ?? [],
            'cta_label' => $this->cta_label,
            'cta_url' => $this->cta_url,
            'image_url' => MediaStorage::url($this->image),
            'gradient' => $this->gradient,
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
            'starts_at' => $this->starts_at?->toISOString(),
            'ends_at' => $this->ends_at?->toISOString(),
            // Active and inside its schedule right now (what visitors actually see).
            'is_live' => $this->is_active
                && (! $this->starts_at || $this->starts_at <= $now)
                && (! $this->ends_at || $this->ends_at >= $now),
        ];
    }
}
