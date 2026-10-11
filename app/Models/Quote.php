<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\QuoteStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Quote extends Model
{
    use SoftDeletes;

    public const DEFAULT_CURRENCY = 'USD';

    /** Eager-loaded wherever a quote is returned to the client. */
    public const RELATIONS = [
        'owner:id,name',
        'deal:id,name',
        'company:id,name',
        'contact:id,first_name,last_name',
    ];

    protected $fillable = [
        'quote_number', 'subject', 'status', 'owner_id', 'deal_id', 'company_id', 'contact_id',
        'issue_date', 'valid_until', 'currency',
        'subtotal', 'discount_total', 'tax_total', 'total',
        'terms', 'notes', 'sent_at', 'accepted_at', 'rejected_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => QuoteStatus::class,
            'issue_date' => 'date',
            'valid_until' => 'date',
            'subtotal' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'total' => 'decimal:2',
            'sent_at' => 'datetime',
            'accepted_at' => 'datetime',
            'rejected_at' => 'datetime',
        ];
    }

    /** Still open (draft / sent) but past its valid-until date. */
    public function isExpired(): bool
    {
        return $this->status->isOpen()
            && $this->valid_until !== null
            && $this->valid_until->lt(today());
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuoteItem::class)->orderBy('position')->orderBy('id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function deal(): BelongsTo
    {
        return $this->belongsTo(Deal::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }
}
