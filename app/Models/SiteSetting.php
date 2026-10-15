<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\FlushesSiteContent;
use Illuminate\Database\Eloquent\Model;

class SiteSetting extends Model
{
    use FlushesSiteContent;

    protected $fillable = ['group', 'data'];

    protected $casts = ['data' => 'array'];
}
