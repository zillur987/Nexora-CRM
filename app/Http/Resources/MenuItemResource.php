<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Services\Cms\MediaStorage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Admin shape: every field, inactive items included, nested children when loaded. */
class MenuItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'parent_id' => $this->parent_id,
            'type' => $this->type->value,
            'label' => $this->label,
            'description' => $this->description,
            'url' => $this->url,
            'target' => $this->target,
            'meta_text' => $this->meta_text,
            'feature_label' => $this->feature_label,
            'feature_title' => $this->feature_title,
            'feature_sub' => $this->feature_sub,
            'feature_url' => $this->feature_url,
            'feature_image_url' => MediaStorage::url($this->feature_image),
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active,
            'children' => self::collection($this->whenLoaded('children')),
        ];
    }
}
