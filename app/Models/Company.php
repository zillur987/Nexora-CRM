<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CompanySize;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Company extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name', 'owner_id', 'parent_id', 'industry_id', 'company_type_id',
        'size', 'annual_revenue', 'currency',
        'phone', 'email', 'website', 'linkedin', 'twitter', 'instagram', 'facebook',
        'billing_street', 'billing_city', 'billing_zip', 'billing_state', 'billing_country',
        'shipping_street', 'shipping_city', 'shipping_zip', 'shipping_state', 'shipping_country',
        'description', 'custom_fields',
    ];

    /** Mirror the DB defaults so freshly created models are fully hydrated. */
    protected $attributes = [
        'currency' => 'USD',
    ];

    protected function casts(): array
    {
        return [
            'size' => CompanySize::class,
            'annual_revenue' => 'decimal:2',
            'custom_fields' => 'array',
        ];
    }

    /** "street, city, state zip, country" with empty parts skipped; null when nothing is filled in. */
    protected function billingAddress(): Attribute
    {
        return Attribute::get(fn () => $this->formatAddress('billing'));
    }

    protected function shippingAddress(): Attribute
    {
        return Attribute::get(fn () => $this->formatAddress('shipping'));
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function industry(): BelongsTo
    {
        return $this->belongsTo(Industry::class);
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(CompanyType::class, 'company_type_id');
    }

    private function formatAddress(string $group): ?string
    {
        $stateAndZip = implode(' ', array_filter(
            [$this->{"{$group}_state"}, $this->{"{$group}_zip"}],
            'filled',
        ));

        $parts = array_filter(
            [$this->{"{$group}_street"}, $this->{"{$group}_city"}, $stateAndZip, $this->{"{$group}_country"}],
            'filled',
        );

        return $parts === [] ? null : implode(', ', $parts);
    }
}
