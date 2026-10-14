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
        'users' => ['label' => 'Users', 'icon' => 'bi-people', 'actions' => ['view', 'create', 'edit']],
        'roles' => ['label' => 'Roles & permissions', 'icon' => 'bi-shield-lock', 'actions' => ['view', 'create', 'edit', 'delete']],
        'documents'  => ['label' => 'Documents',      'icon' => 'bi-folder',       'actions' => ['view', 'create', 'edit', 'delete']],
        'reports'    => ['label' => 'Reports',        'icon' => 'bi-bar-chart',    'actions' => ['view', 'export']],
        'invoices'   => ['label' => 'Invoices',       'icon' => 'bi-receipt',      'actions' => ['view', 'create', 'edit', 'delete', 'export']],
        'payments'   => ['label' => 'Payments',       'icon' => 'bi-credit-card',  'actions' => ['view', 'create', 'edit', 'delete']],
        'expenses'   => ['label' => 'Expenses',       'icon' => 'bi-wallet2',      'actions' => ['view', 'create', 'edit', 'delete']],
        'tickets'    => ['label' => 'Tickets',        'icon' => 'bi-life-preserver','actions' => ['view', 'create', 'edit', 'delete']],
        'knowledge'  => ['label' => 'Knowledge base', 'icon' => 'bi-book',         'actions' => ['view', 'create', 'edit', 'delete']],
        'team'       => ['label' => 'Team members',   'icon' => 'bi-person-badge', 'actions' => ['view', 'create', 'edit', 'delete']],
        'integrations' => ['label' => 'Integrations', 'icon' => 'bi-plug',         'actions' => ['view', 'edit']],
        // Real modules
        'quotes' => ['label' => 'Quotes', 'icon' => 'bi-file-earmark-text', 'actions' => ['view', 'create', 'edit', 'delete']],
        'pipelines' => ['label' => 'Pipelines', 'icon' => 'bi-kanban-fill', 'actions' => ['view', 'create', 'edit', 'delete']],
        'tasks' => ['label' => 'Tasks', 'icon' => 'bi-check2-square', 'actions' => ['view', 'create', 'edit', 'delete']],

        // Sidebar pages that are not built yet (view = show the link)
        'forecasts' => ['label' => 'Forecasts', 'icon' => 'bi-graph-up-arrow', 'actions' => ['view']],
        'campaigns' => ['label' => 'Campaigns', 'icon' => 'bi-megaphone', 'actions' => ['view']],
        'email_templates' => ['label' => 'Email templates', 'icon' => 'bi-envelope-paper', 'actions' => ['view']],
        'segments' => ['label' => 'Segments', 'icon' => 'bi-funnel', 'actions' => ['view']],
        'calendar' => ['label' => 'Calendar', 'icon' => 'bi-calendar3', 'actions' => ['view']],
        'meetings' => ['label' => 'Meetings', 'icon' => 'bi-camera-video', 'actions' => ['view']],
        'notes' => ['label' => 'Notes', 'icon' => 'bi-journal-text', 'actions' => ['view']],
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
