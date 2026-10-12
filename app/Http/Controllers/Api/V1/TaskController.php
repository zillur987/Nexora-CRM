<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tasks\ListTasksRequest;
use App\Http\Requests\Tasks\StoreTaskOptionRequest;
use App\Http\Requests\Tasks\StoreTaskRequest;
use App\Http\Requests\Tasks\UpdateTaskRequest;
use App\Http\Resources\TaskResource;
use App\Models\Task;
use App\Services\Tasks\TaskService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class TaskController extends Controller
{
    public function __construct(private readonly TaskService $service) {}

    public function index(ListTasksRequest $request)
    {
        return TaskResource::collection($this->service->list($request->validated()));
    }

    public function store(StoreTaskRequest $request)
    {
        return TaskResource::make($this->service->create($request->validated(), $request->user()))->response()->setStatusCode(201);
    }

    public function show(Task $task): TaskResource
    {
        return TaskResource::make($this->service->find($task));
    }

    public function update(UpdateTaskRequest $request, Task $task): TaskResource
    {
        return TaskResource::make($this->service->update($task, $request->validated()));
    }

    public function destroy(Task $task): Response
    {
        $this->service->delete($task);
        return response()->noContent();
    }

    /** KPI strip for the list header. */
    public function summary()
    {
        return response()->json(['data' => $this->service->summary()]);
    }

    /** Dropdown data for the form and the filter panel. */
    public function lookups(Request $request)
    {
        return response()->json(['data' => $this->service->lookups($request->user())]);
    }

    /** POST /task-options/{kind}  (kind = type | priority | status) — the "+" buttons in the form. */
    public function storeOption(StoreTaskOptionRequest $request, string $kind)
    {
        $option = $this->service->createOption($kind, $request->validated('name'), $request->validated('color'));

        return response()->json(['data' => [
            'id' => $option->id,
            'name' => $option->name,
            'color' => $option->color,
            ...($kind === 'status' ? ['is_closed' => (bool) $option->is_closed] : []),
        ]], 201);
    }
}
