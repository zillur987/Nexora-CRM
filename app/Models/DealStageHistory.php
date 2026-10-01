<?php // app/Models/DealStageHistory.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DealStageHistory extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['deal_id', 'from_stage_id', 'to_stage_id', 'changed_by', 'seconds_in_previous_stage'];

    public function deal(): BelongsTo
    {
        return $this->belongsTo(Deal::class);
    }
}