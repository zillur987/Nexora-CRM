<?php

declare(strict_types=1);

namespace App\Services\Rbac;

use App\Contracts\Repositories\RoleRepository;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class RoleService
{
    public function __construct(private readonly RoleRepository $roles) {}

    public function list(array $filters): LengthAwarePaginator { return $this->roles->paginate($filters); }

    public function create(array $data, $actor): Role
    {
        $permissionIds = $this->grantable($data['permission_ids'] ?? [], $actor);
        $name = trim($data['name']);

        return $this->roles->create([
            'name' => $name,
            'slug' => $this->uniqueSlug($name),
            'description' => $this->nullIfBlank($data['description'] ?? null),
            'is_system' => false,
        ], $permissionIds);
    }

    public function update(Role $role, array $data, $actor): Role
    {
        $attributes = [];
        if (array_key_exists('description', $data)) $attributes['description'] = $this->nullIfBlank($data['description']);

        // System roles keep their name and slug; the super admin role keeps its permissions.
        if (! $role->is_system && isset($data['name'])) $attributes['name'] = trim($data['name']);

        $permissionIds = null;
        if (array_key_exists('permission_ids', $data) && ! $role->isSuperAdmin()) {
            $this->assertCanTouch($role, $actor);
            $permissionIds = $this->grantable($data['permission_ids'], $actor, $role);
        }

        return $this->roles->update($role, $attributes, $permissionIds);
    }

    public function delete(Role $role): void
    {
        if ($role->is_system) {
            throw ValidationException::withMessages(['role' => 'System roles cannot be deleted.']);
        }

        $count = $role->users()->count();
        if ($count > 0) {
            throw ValidationException::withMessages([
                'role' => "This role is assigned to {$count} ".Str::plural('user', $count).'. Reassign them before deleting it.',
            ]);
        }

        $this->roles->delete($role);
    }

    /**
     * Privilege-escalation guard: a non-super-admin may only add or remove permissions they hold themselves,
     * so someone with "roles.edit" cannot grant themselves (or others) more than they already have.
     *
     * @param array<int,int|string> $ids
     * @return array<int,int>
     */
    private function grantable(array $ids, $actor, ?Role $existing = null): array
    {
        $requested = collect($ids)->map(fn ($id) => (int) $id)->unique()->values();
        if ($actor->isSuperAdmin()) return $requested->all();

        $mine = Permission::query()->whereIn('name', $actor->permissionNames())->pluck('id');
        $current = $existing ? $existing->permissions()->pluck('permissions.id') : collect();

        $changed = $requested->diff($current)->merge($current->diff($requested));
        if ($changed->diff($mine)->isNotEmpty()) $this->deny();

        return $requested->all();
    }

    /** Editing a role that holds permissions you lack would let you strip them, so that is refused too. */
    private function assertCanTouch(Role $role, $actor): void
    {
        if ($actor->isSuperAdmin()) return;
        // Changing a role you hold yourself is also blocked: nobody edits their own access ceiling.
        if ($actor->loadedRoles()->contains('id', $role->id)) {
            throw ValidationException::withMessages(['permission_ids' => 'You cannot change the permissions of a role you hold yourself.']);
        }
    }

    private function deny(): never
    {
        throw ValidationException::withMessages(['permission_ids' => 'You can only grant permissions that you hold yourself.']);
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'role';
        $slug = $base;
        $i = 2;
        while (Role::query()->where('slug', $slug)->exists()) $slug = $base.'-'.$i++;
        return $slug;
    }

    private function nullIfBlank(?string $value): ?string
    {
        $value = $value === null ? null : trim($value);
        return $value === '' ? null : $value;
    }
}
