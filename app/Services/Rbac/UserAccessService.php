<?php

declare(strict_types=1);

namespace App\Services\Rbac;

use App\Models\Role;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

final class UserAccessService
{
    private function userModel(): string { return config('auth.providers.users.model'); }

    public function list(array $filters): LengthAwarePaginator
    {
        $term = filled($filters['search'] ?? null) ? '%'.addcslashes(trim($filters['search']), '%_\\').'%' : null;

        $allowedSorts = ['name', 'email', 'created_at'];
        $sort = in_array($filters['sort'] ?? 'name', $allowedSorts, true) ? ($filters['sort'] ?? 'name') : 'name';
        $direction = ($filters['direction'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

        return $this->userModel()::query()
            ->with('roles:id,name,slug')
            ->when($term, fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', $term)->orWhere('email', 'like', $term)))
            ->when(filled($filters['role_id'] ?? null), fn ($q) => $q->whereHas('roles', fn ($r) => $r->whereKey($filters['role_id'])))
            ->when(($filters['no_role'] ?? null) === '1', fn ($q) => $q->whereDoesntHave('roles'))
            ->orderBy($sort, $direction)
            ->paginate(min(max((int) ($filters['per_page'] ?? 20), 1), 100))
            ->withQueryString();
    }

    public function summary(): array
    {
        $users = $this->userModel()::query();

        return [
            'total' => (clone $users)->count(),
            'with_roles' => (clone $users)->whereHas('roles')->count(),
            'without_roles' => (clone $users)->whereDoesntHave('roles')->count(),
            'super_admins' => (clone $users)->whereHas('roles', fn ($r) => $r->where('slug', config('permissions.super_admin_role')))->count(),
        ];
    }

    /** @param array<int,int> $roleIds */
    public function syncRoles(Model $user, array $roleIds, $actor): Model
    {
        $roleIds = collect($roleIds)->map(fn ($id) => (int) $id)->unique()->values();
        $superId = Role::query()->where('slug', config('permissions.super_admin_role'))->value('id');

        $hadSuper = $superId && $user->roles()->whereKey($superId)->exists();
        $willHaveSuper = $superId && $roleIds->contains($superId);

        // Only a super admin may grant or revoke the super admin role.
        if ($hadSuper !== $willHaveSuper && ! $actor->isSuperAdmin()) {
            throw ValidationException::withMessages(['role_ids' => 'Only a Super Admin can change the Super Admin role.']);
        }

        // A non-super-admin cannot hand out a role that holds permissions they lack.
        if (! $actor->isSuperAdmin()) {
            $mine = $actor->permissionNames();
            $existing = $user->roles()->pluck('roles.id');
            $newRoleIds = $roleIds->diff($existing);
            $roles = Role::query()->with('permissions:id,name')->whereIn('id', $newRoleIds)->get();
            foreach ($roles as $role) {
                if ($role->permissions->pluck('name')->diff($mine)->isNotEmpty()) {
                    throw ValidationException::withMessages(['role_ids' => "You cannot assign “{$role->name}”: it grants permissions you do not hold."]);
                }
            }
        }

        // Never leave the system without a Super Admin.
        if ($hadSuper && ! $willHaveSuper) {
            $others = $this->userModel()::query()->whereKeyNot($user->getKey())->whereHas('roles', fn ($r) => $r->whereKey($superId))->count();
            if ($others === 0) {
                throw ValidationException::withMessages(['role_ids' => 'This is the last Super Admin. Promote another user first.']);
            }
        }

        $user->syncRoles($roleIds->all());

        return $user->load('roles:id,name,slug');
    }
}
