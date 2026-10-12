<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Roles\ListRolesRequest;
use App\Http\Requests\Roles\StoreRoleRequest;
use App\Http\Requests\Roles\UpdateRoleRequest;
use App\Http\Resources\RoleResource;
use App\Models\Permission;
use App\Models\Role;
use App\Services\Rbac\RoleService;
use Illuminate\Http\Response;

class RoleController extends Controller
{
    public function __construct(private readonly RoleService $service) {}

    public function index(ListRolesRequest $request)
    {
        return RoleResource::collection($this->service->list($request->validated()));
    }

    public function store(StoreRoleRequest $request)
    {
        $role = $this->service->create($request->validated(), $request->user());
        return RoleResource::make($role)->response()->setStatusCode(201);
    }

    public function show(Role $role): RoleResource
    {
        return RoleResource::make($role->loadCount(['users', 'permissions'])->load('permissions:id,name'));
    }

    public function update(UpdateRoleRequest $request, Role $role): RoleResource
    {
        return RoleResource::make($this->service->update($role, $request->validated(), $request->user()));
    }

    public function destroy(Role $role): Response
    {
        $this->service->delete($role);
        return response()->noContent();
    }

    /** KPI strip: counts for the roles list header. */
    public function summary()
    {
        dd(4534);
        $userModel = config('auth.providers.users.model');
        dd($userModel);

        return response()->json(['data' => [
            'total' => Role::query()->count(),
            'custom' => Role::query()->where('is_system', false)->count(),
            'permissions' => Permission::query()->count(),
            'users_without_role' => $userModel::query()->whereDoesntHave('roles')->count(),
        ]]);
    }
}
