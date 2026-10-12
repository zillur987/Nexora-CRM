<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Access\ListUsersAccessRequest;
use App\Http\Requests\Access\UpdateUserRolesRequest;
use App\Http\Resources\UserAccessResource;
use App\Models\Role;
use App\Services\Rbac\UserAccessService;

class UserAccessController extends Controller
{
    public function __construct(private readonly UserAccessService $service) {}

    public function index(ListUsersAccessRequest $request)
    {
        return UserAccessResource::collection($this->service->list($request->validated()));
    }

    public function summary()
    {
        return response()->json(['data' => $this->service->summary()]);
    }

    /** Role options for the assignment form. */
    public function lookups()
    {
        return response()->json(['data' => [
            'roles' => Role::query()->orderBy('name')->get(['id', 'name', 'slug', 'description', 'is_system']),
        ]]);
    }

    public function show(int $user): UserAccessResource
    {
        $model = config('auth.providers.users.model')::query()->with('roles:id,name,slug')->findOrFail($user);
        abort_unless(request()->user()->can('users.view'), 403, 'You do not have permission to do that.');

        return UserAccessResource::make($model);
    }

    public function updateRoles(UpdateUserRolesRequest $request, int $user): UserAccessResource
    {
        $model = config('auth.providers.users.model')::query()->findOrFail($user);

        return UserAccessResource::make($this->service->syncRoles($model, $request->validated('role_ids'), $request->user()));
    }
}
