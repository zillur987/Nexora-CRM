<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Services\Rbac\PermissionSynchronizer;
use Illuminate\Database\Seeder;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(PermissionSynchronizer $sync): void
    {
        $sync->syncPermissions();     // permissions first so default roles can reference them
        $sync->seedDefaultRoles();
        $sync->syncPermissions();     // second pass attaches the full registry to super-admin

        // Make the first user a Super Admin so nobody is locked out after installing.
        $first = config('auth.providers.users.model')::query()->orderBy('id')->first();
        $super = \App\Models\Role::query()->where('slug', config('permissions.super_admin_role'))->first();
        if ($first && $super && ! $super->users()->exists()) {
            $first->roles()->syncWithoutDetaching([$super->id]);
        }
    }
}
