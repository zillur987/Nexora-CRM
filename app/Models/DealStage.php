<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DealStageOutcome;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DealStage extends Model
{
    protected $fillable = ['name', 'probability', 'outcome', 'sort_order'];

    protected function casts(): array
    {
        return [
            'probability' => 'integer',
            'sort_order' => 'integer',
            'outcome' => DealStageOutcome::class,
        ];
    }

    public function deals(): HasMany
    {
        return $this->hasMany(Deal::class, 'deal_stage_id');
    }
}
