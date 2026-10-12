<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TaskStatus extends Model
{
    protected $table = 'task_statuses';

    protected $fillable = ['name', 'color', 'sort_order', 'is_default', 'is_active', 'is_closed'];

    protected function casts(): array
    {
        return ['is_default' => 'boolean', 'is_active' => 'boolean', 'is_closed' => 'boolean'];
    }
}
