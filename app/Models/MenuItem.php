<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\FlushesSiteContent;
use App\Enums\MenuItemType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MenuItem extends Model
{
    use FlushesSiteContent;

    protected $fillable = [
        'location', 'parent_id', 'type', 'label', 'description', 'url', 'target',
        'meta_text', 'feature_label', 'feature_title', 'feature_sub', 'feature_url', 'feature_image',
        'sort_order', 'is_active',
    ];

    protected $casts = [
        'type' => MenuItemType::class,
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order')->orderBy('id');
    }

    public function scopeHeader(Builder $query): Builder
    {
        return $query->where('location', 'header');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
