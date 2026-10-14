<?php

declare(strict_types=1);

namespace App\Services\Rbac;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
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

    /**
     * Creates a user and gives them roles and (optionally) extra permissions, all or nothing.
     * The same guards as editing apply: only a Super Admin can hand out Super Admin, and nobody
     * can grant roles or permissions they do not hold themselves.
     *
     * @param array{name:string,email:string,password:string,role_ids?:array<int,int>,permission_ids?:array<int,int>} $data
     */
    public function create(array $data, $actor): Model
    {
        return DB::transaction(function () use ($data, $actor): Model {
            $class = $this->userModel();
            $user = new $class();

            $attributes = [
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
            ];
            // Admin-created accounts are trusted, so they can sign in even if the app requires verified e-mail.
            if (Schema::hasColumn($user->getTable(), 'email_verified_at')) $attributes['email_verified_at'] = now();

            $user->forceFill($attributes)->save();

            // Rolls the whole transaction back (no half-created user) if a guard refuses.
            return $this->syncRoles($user, $data['role_ids'] ?? [], $actor, $data['permission_ids'] ?? null);
        });
    }

    /**
     * Saves a user's roles and, when $permissionIds is not null, their extra (direct) permissions.
     *
     * @param array<int,int> $roleIds
     * @param array<int,int>|null $permissionIds null = leave the user's extra permissions untouched
     */
    public function syncRoles(Model $user, array $roleIds, $actor, ?array $permissionIds = null): Model
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

        // Extra permissions: same anti-escalation rule as roles; you can only add or remove what you hold yourself.
        $extraIds = null;
        if ($permissionIds !== null) {
            $extraIds = collect($permissionIds)->map(fn ($id) => (int) $id)->unique()->values();

            if (! $actor->isSuperAdmin()) {
                $current = $user->directPermissions()->pluck('permissions.id');
                $changed = $extraIds->diff($current)->merge($current->diff($extraIds));
                $mine = Permission::query()->whereIn('name', $actor->permissionNames())->pluck('id');

                if ($changed->diff($mine)->isNotEmpty()) {
                    throw ValidationException::withMessages(['permission_ids' => 'You can only grant or remove permissions that you hold yourself.']);
                }
            }
        }

        DB::transaction(function () use ($user, $roleIds, $extraIds): void {
            $user->syncRoles($roleIds->all());
            if ($extraIds !== null) $user->syncDirectPermissions($extraIds->all());
        });

        return $user->load('roles:id,name,slug', 'directPermissions:id,name');
    }
}
