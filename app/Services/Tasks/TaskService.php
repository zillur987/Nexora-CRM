<?php

declare(strict_types=1);

namespace App\Services\Tasks;

use App\Contracts\Repositories\TaskRepository;
use App\Models\Task;
use App\Models\TaskPriority;
use App\Models\TaskStatus;
use App\Models\TaskType;
use App\Support\TaskRelations;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class TaskService
{
    /** URL segment => model. */
    public const OPTION_MODELS = ['type' => TaskType::class, 'priority' => TaskPriority::class, 'status' => TaskStatus::class];

    public function __construct(private readonly TaskRepository $tasks) {}

    public function list(array $filters): LengthAwarePaginator { return $this->tasks->paginate($filters); }
    public function find(Task $task): Task { return $this->tasks->find($task); }
    public function summary(): array { return $this->tasks->summary(); }
    public function delete(Task $task): void { $this->tasks->delete($task); }

    public function create(array $data, $actor): Task
    {
        $data = $this->clean($data);
        $data['created_by'] = $actor->getKey();

        // Creating without choosing: assign to yourself and use the default status.
        if (! array_key_exists('assignee_id', $data)) $data['assignee_id'] = $actor->getKey();
        if (empty($data['task_status_id'])) $data['task_status_id'] = TaskStatus::query()->where('is_default', true)->value('id');
        if (empty($data['task_priority_id'])) $data['task_priority_id'] = TaskPriority::query()->where('is_default', true)->value('id');

        $data['completed_at'] = $this->completedAt($data['task_status_id'] ?? null, null);

        return $this->tasks->create($data);
    }

    public function update(Task $task, array $data): Task
    {
        $data = $this->clean($data);

        if (array_key_exists('task_status_id', $data)) {
            $data['completed_at'] = $this->completedAt($data['task_status_id'], $task->completed_at);
        }

        return $this->tasks->update($task, $data);
    }

    /** Lookup payload for the form and the list filters. */
    public function lookups($actor): array
    {
        $label = config('tasks.user_label', 'name');
        $limit = (int) config('tasks.lookup_limit', 500);
        $userModel = TaskRelations::userModel();

        $option = function (string $class, bool $withClosed = false): array {
            return $class::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get()
                ->map(function (Model $m) use ($withClosed): array {
                    $row = ['id' => $m->id, 'name' => $m->name, 'color' => $m->color];
                    if ($withClosed) $row['is_closed'] = (bool) $m->is_closed;
                    return $row;
                })->all();
        };

        return [
            'users' => $userModel::query()->select(['id', $label])->orderBy($label)->limit($limit)->get()
                ->map(fn ($u) => ['id' => $u->id, 'name' => (string) $u->{$label}])->all(),
            'companies' => TaskRelations::options('company'),
            'contacts' => TaskRelations::options('contact'),
            'opportunities' => TaskRelations::options('opportunity'),
            'quotes' => TaskRelations::options('quote'),
            'types' => $option(TaskType::class),
            'priorities' => $option(TaskPriority::class),
            'statuses' => $option(TaskStatus::class, true),
            'defaults' => [
                'assignee_id' => $actor->getKey(),
                'task_status_id' => TaskStatus::query()->where('is_default', true)->value('id'),
                'task_priority_id' => TaskPriority::query()->where('is_default', true)->value('id'),
            ],
        ];
    }

    /** "+" next to Task Type / Priority / Status: add a new option without leaving the form. */
    public function createOption(string $kind, string $name, ?string $color): Model
    {
        $class = self::OPTION_MODELS[$kind] ?? throw ValidationException::withMessages(['kind' => 'Unknown option type.']);
        $name = trim($name);

        if ($class::query()->whereRaw('lower(name) = ?', [Str::lower($name)])->exists()) {
            throw ValidationException::withMessages(['name' => 'That name already exists.']);
        }

        return $class::query()->create([
            'name' => $name,
            'color' => $color,
            'sort_order' => ((int) $class::query()->max('sort_order')) + 10,
        ]);
    }

    /** Trim text, turn blank strings into null. */
    private function clean(array $data): array
    {
        foreach (['name', 'description'] as $field) {
            if (array_key_exists($field, $data) && is_string($data[$field])) {
                $data[$field] = trim($data[$field]);
                if ($field === 'description' && $data[$field] === '') $data[$field] = null;
            }
        }

        return $data;
    }

    /** Moving into a closed status stamps the completion time; moving back out clears it. */
    private function completedAt(null|int|string $statusId, $current)
    {
        if (! $statusId || ! TaskStatus::query()->whereKey($statusId)->where('is_closed', true)->exists()) return null;
        return $current ?? now();
    }
}
