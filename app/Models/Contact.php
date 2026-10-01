<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ContactStatus;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Contact extends Model
{
    use HasUuids;
    use SoftDeletes;

    protected $fillable = ['first_name', 'last_name', 'email', 'phone', 'company', 'status'];

    protected function casts(): array
    {
        return ['status' => ContactStatus::class];
    }

    protected function fullName(): Attribute
    {
        return Attribute::get(fn () => "{$this->first_name} {$this->last_name}");
    }

    public function deals(): HasMany
    {
        return $this->hasMany(Deal::class);
    }
}
