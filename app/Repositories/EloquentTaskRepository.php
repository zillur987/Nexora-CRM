<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\Repositories\TaskRepository;
use App\Models\Task;
use App\Support\TaskRelations;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class EloquentTaskRepository implements TaskRepository
{
    private const FILTER_COLUMNS = [
        'assignee_id', 'company_id', 'contact_id', 'opportunity_id', 'quote_id',
        'task_type_id', 'task_priority_id', 'task_status_id',
    ];

    public function paginate(array $filters): LengthAwarePaginator
    {
        $allowedSorts = ['name', 'due_date', 'created_at', 'updated_at'];
        $sort = in_array($filters['sort'] ?? 'created_at', $allowedSorts, true) ? ($filters['sort'] ?? 'created_at') : 'created_at';
        $direction = ($filters['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        $query = Task::query()->with($this->with())->search($filters['search'] ?? null);

        foreach (self::FILTER_COLUMNS as $column) {
            if (filled($filters[$column] ?? null)) $query->where($column, (int) $filters[$column]);
        }

        match ($filters['scope'] ?? '') {
            'open' => $query->open(),
            'overdue' => $query->overdue(),
            'today' => $query->dueToday(),
            'completed' => $query->completed(),
            default => null,
        };

        if ($sort === 'due_date') $query->orderByRaw('due_date is null'); // tasks without a date always last

        return $query->orderBy($sort, $direction)
            ->orderByDesc('id')
            ->paginate(min(max((int) ($filters['per_page'] ?? 20), 1), 100))
            ->withQueryString();
    }

    public function find(Task $task): Task
    {
        return $task->load($this->with());
    }

    public function create(array $data): Task
    {
        return $this->find(Task::query()->create($data));
    }

    public function update(Task $task, array $data): Task
    {
        $task->fill($data)->save();
        return $this->find($task->refresh());
    }

    public function delete(Task $task): void
    {
        $task->delete();
    }

    public function summary(): array
    {
        return [
            'total' => Task::query()->count(),
            'open' => Task::query()->open()->count(),
            'overdue' => Task::query()->overdue()->count(),
            'due_today' => Task::query()->dueToday()->count(),
            'completed' => Task::query()->completed()->count(),
        ];
    }

    /** @return array<int,string> */
    private function with(): array
    {
        return ['assignee', 'type', 'priority', 'status', ...TaskRelations::available()];
    }
}
