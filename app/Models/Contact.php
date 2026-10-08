<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Contact extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'first_name', 'last_name', 'owner_id', 'email', 'birthday',
        'company_id', 'job_title', 'phone', 'department',
        'industry_id', 'contact_source_id', 'contact_stage_id',
        'present_address', 'present_city', 'present_zip', 'present_state', 'present_country',
        'permanent_address', 'permanent_city', 'permanent_zip', 'permanent_state', 'permanent_country',
        'twitter', 'linkedin', 'description',
    ];

    protected function casts(): array
    {
        return [
            'birthday' => 'date',
        ];
    }

    protected function fullName(): Attribute
    {
        return Attribute::get(fn () => trim("{$this->first_name} {$this->last_name}"));
    }

    /** "address, city, state zip, country" with empty parts skipped; null when nothing is filled in. */
    protected function presentFullAddress(): Attribute
    {
        return Attribute::get(fn () => $this->formatAddress('present'));
    }

    protected function permanentFullAddress(): Attribute
    {
        return Attribute::get(fn () => $this->formatAddress('permanent'));
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function industry(): BelongsTo
    {
        return $this->belongsTo(Industry::class);
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(ContactSource::class, 'contact_source_id');
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(ContactStage::class, 'contact_stage_id');
    }

    /** Keep: the deals module links deals to contacts. */
    public function deals(): HasMany
    {
        return $this->hasMany(Deal::class);
    }

    private function formatAddress(string $group): ?string
    {
        $stateAndZip = implode(' ', array_filter(
            [$this->{"{$group}_state"}, $this->{"{$group}_zip"}],
            'filled',
        ));

        $parts = array_filter(
            [$this->{"{$group}_address"}, $this->{"{$group}_city"}, $stateAndZip, $this->{"{$group}_country"}],
            'filled',
        );

        return $parts === [] ? null : implode(', ', $parts);
    }
}
