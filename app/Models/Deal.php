<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DealStage;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Deal extends Model
{
    use HasUuids;

    protected $fillable = [
        'title', 'amount', 'currency', 'stage',
        'expected_close_date', 'closed_at', 'contact_id',
    ];

    protected function casts(): array
    {
        return [
            'stage' => DealStage::class,
            'amount' => 'decimal:2',
            'expected_close_date' => 'date',
            'closed_at' => 'datetime',
        ];
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class)->withTrashed();
    }
}
