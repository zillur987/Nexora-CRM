<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Pipeline extends Model
{
    use SoftDeletes;

    protected $fillable = ['name', 'description', 'is_default', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return ['is_default' => 'boolean', 'is_active' => 'boolean', 'sort_order' => 'integer'];
    }

    public function stages(): HasMany
    {
        return $this->hasMany(DealStage::class, 'pipeline_id')->orderBy('sort_order')->orderBy('id');
    }

    public function deals(): HasMany
    {
        return $this->hasMany(Deal::class, 'pipeline_id');
    }
}
