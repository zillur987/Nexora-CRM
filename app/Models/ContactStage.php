<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContactStage extends Model
{
    protected $fillable = ['name'];

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class, 'contact_stage_id');
    }
}
