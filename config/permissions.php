<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Role & permission registry
|--------------------------------------------------------------------------
| Single source of truth. Every permission is "<module>.<action>".
| After editing this file run:  php artisan rbac:sync
|
| To protect a new module: add it under "modules", run rbac:sync, then guard
| its routes with ->middleware('permission:<module>.<action>') and its UI with
| v-can="'<module>.<action>'".
*/

return [
    /** Slug of the role that bypasses every check. Never deletable or editable. */
    'super_admin_role' => 'super-admin',

    'actions' => [
        'view' => 'View',
        'create' => 'Create',
        'edit' => 'Edit',
        'delete' => 'Delete',
        'export' => 'Export',
    ],

    'modules' => [
        'dashboard' => ['label' => 'Dashboard', 'icon' => 'bi-speedometer2', 'actions' => ['view']],
        'leads' => ['label' => 'Leads', 'icon' => 'bi-funnel', 'actions' => ['view', 'create', 'edit', 'delete', 'export']],
        'contacts' => ['label' => 'Contacts', 'icon' => 'bi-person-lines-fill', 'actions' => ['view', 'create', 'edit', 'delete', 'export']],
        'companies' => ['label' => 'Companies', 'icon' => 'bi-building', 'actions' => ['view', 'create', 'edit', 'delete', 'export']],
        'deals' => ['label' => 'Deals', 'icon' => 'bi-currency-dollar', 'actions' => ['view', 'create', 'edit', 'delete', 'export']],
        'products' => ['label' => 'Products', 'icon' => 'bi-box-seam', 'actions' => ['view', 'create', 'edit', 'delete', 'export']],
        'users' => ['label' => 'Users', 'icon' => 'bi-people', 'actions' => ['view', 'edit']],
        'roles' => ['label' => 'Roles & permissions', 'icon' => 'bi-shield-lock', 'actions' => ['view', 'create', 'edit', 'delete']],
    ],

    /*
    | Roles created by the seeder when they do not exist yet (existing roles are never overwritten).
    | Patterns: '*' = everything, 'leads.*' = a whole module, 'products.view' = one permission.
    */
    'default_roles' => [
        'super-admin' => [
            'name' => 'Super Admin',
            'description' => 'Unrestricted access to everything, including roles and users.',
            'permissions' => ['*'],
        ],
        'admin' => [
            'name' => 'Admin',
            'description' => 'Manages all CRM data and users. Cannot delete roles.',
            'permissions' => ['*'],
            'except' => ['roles.delete'],
        ],
        'manager' => [
            'name' => 'Manager',
            'description' => 'Full access to CRM records; can see users but not change access.',
            'permissions' => ['dashboard.view', 'leads.*', 'contacts.*', 'companies.*', 'deals.*', 'products.*', 'users.view'],
        ],
        'sales-rep' => [
            'name' => 'Sales Rep',
            'description' => 'Works leads, contacts, companies and deals. Read-only on products.',
            'permissions' => [
                'dashboard.view',
                'leads.view', 'leads.create', 'leads.edit',
                'contacts.view', 'contacts.create', 'contacts.edit',
                'companies.view', 'companies.create', 'companies.edit',
                'deals.view', 'deals.create', 'deals.edit',
                'products.view',
            ],
        ],
        'viewer' => [
            'name' => 'Viewer',
            'description' => 'Read-only access to CRM records.',
            'permissions' => ['dashboard.view', 'leads.view', 'contacts.view', 'companies.view', 'deals.view', 'products.view'],
        ],
    ],
];
