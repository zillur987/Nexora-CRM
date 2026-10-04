<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\LeadSource;
use App\Enums\LeadStatus;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Lead extends Model
{
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    protected $fillable = [
        'first_name', 'last_name', 'email', 'phone', 'company', 'job_title',
        'source', 'status', 'score', 'estimated_value', 'currency', 'notes',
        'assigned_to', 'converted_contact_id', 'last_contacted_at', 'converted_at',
    ];

    /** Mirror the DB defaults so freshly created models are fully hydrated. */
    protected $attributes = [
        'status' => 'new',
        'source' => 'other',
        'score' => 0,
        'currency' => 'USD',
    ];

    protected function casts(): array
    {
        return [
            'status' => LeadStatus::class,
            'source' => LeadSource::class,
            'score' => 'integer',
            'estimated_value' => 'decimal:2',
            'last_contacted_at' => 'datetime',
            'converted_at' => 'datetime',
        ];
    }

    protected function fullName(): Attribute
    {
        return Attribute::get(fn () => "{$this->first_name} {$this->last_name}");
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function convertedContact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'converted_contact_id')->withTrashed();
    }
}
