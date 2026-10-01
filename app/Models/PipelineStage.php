<?php // app/Models/PipelineStage.php

namespace App\Models;

use App\Enums\StageType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PipelineStage extends Model
{
    protected $fillable = ['pipeline_id', 'name', 'position', 'probability', 'type', 'color'];

    protected function casts(): array
    {
        return ['type' => StageType::class];
    }

    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(Pipeline::class);
    }

    public function deals(): HasMany
    {
        return $this->hasMany(Deal::class, 'stage_id')->orderBy('position');
    }

    public function isClosed(): bool
    {
        return $this->type !== StageType::Open;
    }
}