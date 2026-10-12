<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\TaskPriority;
use App\Models\TaskStatus;
use App\Models\TaskType;
use Illuminate\Database\Seeder;

/** Default Task Type / Priority / Status values. Existing rows (matched by name) are never overwritten. */
class TaskOptionsSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            [TaskType::class, config('tasks.default_types', [])],
            [TaskPriority::class, config('tasks.default_priorities', [])],
            [TaskStatus::class, config('tasks.default_statuses', [])],
        ] as [$model, $rows]) {
            foreach (array_values($rows) as $i => $row) {
                $attributes = ['color' => $row[1] ?? null, 'sort_order' => ($i + 1) * 10, 'is_default' => (bool) ($row[2] ?? false)];
                if ($model === TaskStatus::class) $attributes['is_closed'] = (bool) ($row[3] ?? false);

                $model::query()->firstOrCreate(['name' => $row[0]], $attributes);
            }
        }
    }
}
