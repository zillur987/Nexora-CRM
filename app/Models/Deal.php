<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DealPriority;
use App\Enums\DealStageOutcome;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Deal extends Model
{
    use SoftDeletes;

    /** Used when a deal has an amount but no currency yet. */
    public const DEFAULT_CURRENCY = 'USD';

    /** Eager-loaded wherever a deal is returned to the client (list, show, create, update). */
    public const RELATIONS = [
        'owner:id,name',
        'company:id,name',
        'contact:id,first_name,last_name',
        'leadSource:id,name',
        'type:id,name',
        'stage:id,name,probability,outcome',
    ];

    protected $fillable = [
        'name', 'owner_id', 'company_id', 'contact_id',
        'amount', 'currency', 'probability',
        'expected_close_date', 'actual_close_date',
        'deal_stage_id', 'deal_type_id', 'lead_source_id',
        'priority', 'next_step', 'lost_reason', 'description',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'probability' => 'integer',
            'expected_close_date' => 'date',
            'actual_close_date' => 'date',
            'priority' => DealPriority::class,
        ];
    }

    /** amount x probability: what the deal is worth in a forecast. Null until both are known. */
    protected function weightedAmount(): Attribute
    {
        return Attribute::get(function () {
            if ($this->amount === null || $this->probability === null) {
                return null;
            }

            return round((float) $this->amount * $this->probability / 100, 2);
        });
    }

    /** Open deal whose expected close date has passed. Needs the `stage` relation. */
    public function isOverdue(): bool
    {
        return $this->stage?->outcome === DealStageOutcome::Open
            && $this->expected_close_date !== null
            && $this->expected_close_date->lt(today());
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /** Lead sources are shared with contacts (one list to maintain). */
    public function leadSource(): BelongsTo
    {
        return $this->belongsTo(ContactSource::class, 'lead_source_id');
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(DealType::class, 'deal_type_id');
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(DealStage::class, 'deal_stage_id');
    }
}
