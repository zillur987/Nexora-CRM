<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CompanyType extends Model
{
    protected $fillable = ['name'];

    public function companies(): HasMany
    {
        return $this->hasMany(Company::class);
    }
}
