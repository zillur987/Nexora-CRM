<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TaskPriority extends Model
{
    protected $table = 'task_priorities';

    protected $fillable = ['name', 'color', 'sort_order', 'is_default', 'is_active'];

    protected function casts(): array
    {
        return ['is_default' => 'boolean', 'is_active' => 'boolean'];
    }
}
