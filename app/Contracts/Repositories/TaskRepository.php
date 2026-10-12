<?php

declare(strict_types=1);

namespace App\Contracts\Repositories;

use App\Models\Task;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface TaskRepository
{
    public function paginate(array $filters): LengthAwarePaginator;
    public function find(Task $task): Task;
    public function create(array $data): Task;
    public function update(Task $task, array $data): Task;
    public function delete(Task $task): void;
    /** @return array{total:int,open:int,overdue:int,due_today:int,completed:int} */
    public function summary(): array;
}
