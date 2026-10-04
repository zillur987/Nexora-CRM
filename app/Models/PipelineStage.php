<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PipelineStage extends Model
{
    use HasUuids;

    protected $fillable = [
        'pipeline_id', 'name', 'slug', 'position', 'color', 'probability', 'is_won', 'is_lost',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer', 'probability' => 'integer',
            'is_won' => 'boolean', 'is_lost' => 'boolean',
        ];
    }

    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(Pipeline::class);
    }

    public function deals(): HasMany
    {
        return $this->hasMany(Deal::class, 'pipeline_stage_id');
    }
}
