<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = collect(['contacts', 'companies', 'deals'])
            ->flatMap(fn ($m) => collect(['view', 'view_all', 'create', 'update', 'update_all', 'delete'])
                ->map(fn ($a) => "{$m}.{$a}"))
            ->each(fn ($p) => Permission::findOrCreate($p, 'web'));

        Role::findOrCreate('admin', 'web')->syncPermissions($permissions);
        Role::findOrCreate('sales_rep', 'web')->syncPermissions([
            'contacts.view', 'contacts.create', 'contacts.update',
            'companies.view', 'companies.create','deals.view', 'deals.create', 'deals.update',
        ]);
    }
}
