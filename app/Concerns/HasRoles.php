<?php

declare(strict_types=1);

namespace App\Concerns;

use App\Models\Role;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;

/**
 * Add to App\Models\User:   use App\Concerns\HasRoles;
 * Roles and permissions are loaded once per request and memoised on the instance.
 */
trait HasRoles
{
    protected ?Collection $rbacRoles = null;
    protected ?Collection $rbacPermissions = null;

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_user')->withTimestamps();
    }

    /** @return Collection<int, Role> */
    public function loadedRoles(): Collection
    {
        return $this->rbacRoles ??= $this->roles()->with('permissions:id,name')->get();
    }

    public function isSuperAdmin(): bool
    {
        return $this->loadedRoles()->contains(fn (Role $role) => $role->isSuperAdmin());
    }

    public function hasRole(string ...$slugs): bool
    {
        return $this->loadedRoles()->pluck('slug')->intersect($slugs)->isNotEmpty();
    }

    /** @return Collection<int, string> every permission name this user holds (all of them for a super admin) */
    public function permissionNames(): Collection
    {
        if ($this->rbacPermissions !== null) return $this->rbacPermissions;

        if ($this->isSuperAdmin()) {
            return $this->rbacPermissions = collect(\App\Support\PermissionRegistry::names());
        }

        return $this->rbacPermissions = $this->loadedRoles()
            ->flatMap(fn (Role $role) => $role->permissions->pluck('name'))
            ->unique()
            ->values();
    }

    public function hasPermission(string $permission): bool
    {
        return $this->isSuperAdmin() || $this->permissionNames()->contains($permission);
    }

    public function hasAnyPermission(string ...$permissions): bool
    {
        return $this->isSuperAdmin() || $this->permissionNames()->intersect($permissions)->isNotEmpty();
    }

    /** @param array<int,int> $roleIds */
    public function syncRoles(array $roleIds): void
    {
        $this->roles()->sync($roleIds);
        $this->flushRbacCache();
    }

    public function flushRbacCache(): void
    {
        $this->rbacRoles = null;
        $this->rbacPermissions = null;
        $this->unsetRelation('roles');
    }
}
