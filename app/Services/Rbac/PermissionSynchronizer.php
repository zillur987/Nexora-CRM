<?php

declare(strict_types=1);

namespace App\Services\Rbac;

use App\Models\Permission;
use App\Models\Role;
use App\Support\PermissionRegistry;
use Illuminate\Support\Facades\DB;

/** Keeps the permissions table in step with config/permissions.php and creates the default roles. */
final class PermissionSynchronizer
{
    /** @return array{created:int,updated:int,pruned:int} */
    public function syncPermissions(bool $prune = false): array
    {
        $created = $updated = 0;
        $rows = PermissionRegistry::all();

        DB::transaction(function () use ($rows, &$created, &$updated): void {
            foreach ($rows as $row) {
                $permission = Permission::query()->firstOrNew(['name' => $row['name']]);
                $isNew = ! $permission->exists;
                $permission->fill($row)->save();
                $isNew ? $created++ : ($permission->wasChanged() ? $updated++ : null);
            }
        });

        $pruned = $prune ? Permission::query()->whereNotIn('name', array_column($rows, 'name'))->delete() : 0;

        // Super admin always mirrors the full registry.
        $super = Role::query()->where('slug', config('permissions.super_admin_role'))->first();
        $super?->permissions()->sync(Permission::query()->pluck('id'));

        return ['created' => $created, 'updated' => $updated, 'pruned' => (int) $pruned];
    }

    /** Creates missing default roles; roles that already exist are left exactly as the admin configured them. */
    public function seedDefaultRoles(): int
    {
        $made = 0;

        foreach (config('permissions.default_roles', []) as $slug => $def) {
            $role = Role::query()->firstOrNew(['slug' => $slug]);
            if ($role->exists) continue;

            $role->fill(['name' => $def['name'], 'description' => $def['description'] ?? null, 'is_system' => true])->save();
            $names = PermissionRegistry::expand($def['permissions'] ?? [], $def['except'] ?? []);
            $role->permissions()->sync(Permission::query()->whereIn('name', $names)->pluck('id'));
            $made++;
        }

        return $made;
    }
}
