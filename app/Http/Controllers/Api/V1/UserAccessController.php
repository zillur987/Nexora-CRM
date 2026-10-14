<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Access\ListUsersAccessRequest;
use App\Http\Requests\Access\StoreUserRequest;
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
        $roles = Role::query()->with('permissions:id')->orderBy('name')->get(['id', 'name', 'slug', 'description', 'is_system'])
            ->map(fn (Role $r) => [
                'id' => $r->id,
                'name' => $r->name,
                'slug' => $r->slug,
                'description' => $r->description,
                'is_system' => $r->is_system,
                'is_super_admin' => $r->isSuperAdmin(),
                'permission_ids' => $r->permissions->pluck('id')->values(),   // lets the form preview what each role grants
            ]);

        return response()->json(['data' => ['roles' => $roles]]);
    }

    public function show(int $user): UserAccessResource
    {
        $model = config('auth.providers.users.model')::query()->with('roles:id,name,slug', 'directPermissions:id,name')->findOrFail($user);
        abort_unless(request()->user()->can('users.view'), 403, 'You do not have permission to do that.');

        return UserAccessResource::make($model);
    }

    public function store(StoreUserRequest $request)
    {
        $user = $this->service->create($request->validated(), $request->user());

        return UserAccessResource::make($user)->response()->setStatusCode(201);
    }

    public function updateRoles(UpdateUserRolesRequest $request, int $user): UserAccessResource
    {
        $model = config('auth.providers.users.model')::query()->findOrFail($user);

        return UserAccessResource::make($this->service->syncRoles($model, $request->validated('role_ids'), $request->user(), $request->validated('permission_ids')));
    }
}
