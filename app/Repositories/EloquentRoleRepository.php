<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Contracts\Repositories\RoleRepository;
use App\Models\Role;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final class EloquentRoleRepository implements RoleRepository
{
    public function paginate(array $filters): LengthAwarePaginator
    {
        $allowedSorts = ['name', 'created_at', 'users_count', 'permissions_count'];
        $sort = in_array($filters['sort'] ?? 'name', $allowedSorts, true) ? ($filters['sort'] ?? 'name') : 'name';
        $direction = ($filters['direction'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

        return Role::query()
            ->withCount(['users', 'permissions'])
            ->search($filters['search'] ?? null)
            ->when(isset($filters['is_system']) && $filters['is_system'] !== '', fn ($q) => $q->where('is_system', (bool) $filters['is_system']))
            ->orderBy($sort, $direction)
            ->paginate(min(max((int) ($filters['per_page'] ?? 20), 1), 100))
            ->withQueryString();
    }

    public function create(array $data, array $permissionIds): Role
    {
        return DB::transaction(function () use ($data, $permissionIds): Role {
            $role = Role::query()->create($data);
            $role->permissions()->sync($permissionIds);
            return $this->fresh($role);
        });
    }

    public function update(Role $role, array $data, ?array $permissionIds): Role
    {
        return DB::transaction(function () use ($role, $data, $permissionIds): Role {
            $role->fill($data)->save();
            if ($permissionIds !== null) $role->permissions()->sync($permissionIds);
            return $this->fresh($role);
        });
    }

    public function delete(Role $role): void
    {
        $role->delete();
    }

    private function fresh(Role $role): Role
    {
        return $role->fresh()->loadCount(['users', 'permissions'])->load('permissions:id,name');
    }
}
