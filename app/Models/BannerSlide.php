<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\FlushesSiteContent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class BannerSlide extends Model
{
    use FlushesSiteContent;

    protected $fillable = [
        'name', 'location_label', 'title_lines', 'text', 'facts', 'cta_label', 'cta_url',
        'image', 'gradient', 'sort_order', 'is_active', 'starts_at', 'ends_at',
    ];

    protected $casts = [
        'title_lines' => 'array',
        'facts' => 'array',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    /** Active and inside its (optional) schedule window. */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()));
    }
}
