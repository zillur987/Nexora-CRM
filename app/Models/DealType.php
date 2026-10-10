<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DealType extends Model
{
    protected $fillable = ['name'];

    public function deals(): HasMany
    {
        return $this->hasMany(Deal::class, 'deal_type_id');
    }
}
