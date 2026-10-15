import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import AppLayout from '@/layouts/AppLayout.vue';
import { usePermissionStore } from '@/stores/permissions';

const LeadList = () => import('@/views/leads/LeadListView.vue');
const CompanyList = () => import('@/views/companies/CompanyListView.vue');
const ContactList = () => import('@/views/contacts/ContactListView.vue');
const DealList = () => import('@/views/deals/DealListView.vue');
const ProductList = () => import('@/views/products/ProductListView.vue');
const QuoteList = () => import('@/views/quotes/QuoteListView.vue');
const RoleList = () => import('@/views/roles/RoleListView.vue');
const UserAccessList = () => import('@/views/users/UserAccessView.vue');
const Forbidden = () => import('@/views/errors/ForbiddenView.vue');
const TaskList = () => import('@/views/tasks/TaskListView.vue');

const routes = [
    {
        path: '/login',
        name: 'login',
        component: () => import('@/views/LoginView.vue'),
        meta: { guest: true },
    },

    {
        path: '/',
        component: AppLayout,
        meta: { requiresAuth: true },

        children: [
            {
                path: '',
                name: 'dashboard',
                meta: { permission: 'dashboard.view' },
                component: () => import('@/views/DashboardView.vue'),
            },

            // =====================================================
            // LEADS
            // =====================================================

            {
                path: 'leads',
                name: 'leads.index',
                meta: { permission: 'leads.view' },
                component: LeadList,
            },

            {
                path: 'leads/new',
                name: 'leads.create',
                meta: { permission: 'leads.create' },
                component: LeadList,
            },

            {
                path: 'leads/:id',
                name: 'leads.show',
                meta: { permission: 'leads.view' },
                component: () =>
                    import('@/views/leads/LeadDetailView.vue'),
                props: true,
            },

            {
                path: 'leads/:id/edit',
                name: 'leads.edit',
                meta: { permission: 'leads.edit' },
                component: LeadList,
            },

            // =====================================================
            // COMPANIES
            // =====================================================

            {
                path: 'companies',
                name: 'companies.index',
                meta: { permission: 'companies.view' },
                component: CompanyList,
            },

            {
                // Create / edit render the list with the drawer open (same pattern as leads).
                path: 'companies/new',
                name: 'companies.create',
                meta: { permission: 'companies.create' },
                component: CompanyList,
            },

            {
                path: 'companies/:id',
                name: 'companies.show',
                meta: { permission: 'companies.view' },
                component: () =>
                    import('@/views/companies/CompanyDetailView.vue'),
                props: true,
            },

            {
                path: 'companies/:id/edit',
                name: 'companies.edit',
                meta: { permission: 'companies.edit' },
                component: CompanyList,
            },

            // =====================================================
            // CONTACTS
            // =====================================================

            {
                path: 'contacts',
                name: 'contacts.index',
                meta: { permission: 'contacts.view' },
                component: ContactList,
            },

            {
                path: 'contacts/new',
                name: 'contacts.create',
                meta: { permission: 'contacts.create' },
                component: ContactList,
            },

            {
                path: 'contacts/:id',
                name: 'contacts.show',
                meta: { permission: 'contacts.view' },
                component: () => import('@/views/contacts/ContactDetailView.vue'),
                props: true,
            },

            {
                path: 'contacts/:id/edit',
                name: 'contacts.edit',
                meta: { permission: 'contacts.edit' },
                component: ContactList,
            },


            // =====================================================
            // DEALS
            // =====================================================

            {
                path: 'deals',
                name: 'deals.index',
                meta: { permission: 'deals.view' },
                component: DealList,
            },

            {
                path: 'deals/new',
                name: 'deals.create',
                meta: { permission: 'deals.create' },
                component: DealList,
            },

            {
                path: 'deals/:id',
                name: 'deals.show',
                meta: { permission: 'deals.view' },
                component: () => import('@/views/deals/DealDetailView.vue'),
                props: true,
            },

            {
                path: 'deals/:id/edit',
                name: 'deals.edit',
                meta: { permission: 'deals.edit' },
                component: DealList,
            },
            // =====================================================
            // PIPELINES
            // =====================================================

            {
                path: 'pipelines',
                name: 'pipelines.index',
                meta: { permission: 'pipelines.view' },
                component: () => import('@/views/pipelines/PipelineListView.vue'),
            },

            // =====================================================
            // PRODUCTS
            // =====================================================

            {
                path: 'products',
                name: 'products.index',
                meta: { permission: 'products.view' },
                component: ProductList,
            },

            {
                path: 'products/new',
                name: 'products.create',
                meta: { permission: 'products.create' },
                component: ProductList,
            },

            {
                path: 'products/:id',
                name: 'products.show',
                meta: { permission: 'products.view' },
                component: () => import('@/views/products/ProductDetailView.vue'),
                props: true,
            },

            {
                path: 'products/:id/edit',
                name: 'products.edit',
                meta: { permission: 'products.edit' },
                component: ProductList,
            },

            // =====================================================
            // QUOTES
            // =====================================================

            {
                path: 'quotes',
                name: 'quotes.index',
                meta: { permission: 'quotes.view' },
                component: QuoteList,
            },

            {
                path: 'quotes/new',
                name: 'quotes.create',
                meta: { permission: 'quotes.create' },
                component: QuoteList,
            },

            {
                path: 'quotes/:id',
                name: 'quotes.show',
                meta: { permission: 'quotes.view' },
                component: () => import('@/views/quotes/QuoteDetailView.vue'),
                props: true,
            },

            {
                path: 'quotes/:id/edit',
                name: 'quotes.edit',
                meta: { permission: 'quotes.edit' },
                component: QuoteList,
            },

            // =====================================================
            // TASKS
            // =====================================================

            { path: 'tasks', name: 'tasks.index', component: TaskList, meta: { permission: 'tasks.view' } },
            { path: 'tasks/new', name: 'tasks.create', component: TaskList, meta: { permission: 'tasks.create' } },
            { path: 'tasks/:id', name: 'tasks.show', component: TaskList, meta: { permission: 'tasks.view' } },
            { path: 'tasks/:id/edit', name: 'tasks.edit', component: TaskList, meta: { permission: 'tasks.edit' } },

            // =====================================================
            // ROLES & PERMISSIONS
            // =====================================================

            { path: 'roles', name: 'roles.index', component: RoleList, meta: { permission: 'roles.view' } },
            { path: 'roles/new', name: 'roles.create', component: RoleList, meta: { permission: 'roles.create' } },
            { path: 'roles/:id', name: 'roles.show', component: RoleList, meta: { permission: 'roles.view' } },
            { path: 'roles/:id/edit', name: 'roles.edit', component: RoleList, meta: { permission: 'roles.edit' } },

            { path: 'cms/menus',     name: 'cms.menus',     component: () => import('@/views/cms/MenuManagerView.vue'),   meta: { permission: 'menus.view' } },
            { path: 'cms/banners',   name: 'cms.banners',   component: () => import('@/views/cms/BannerSlidesView.vue'),  meta: { permission: 'banners.view' } },
            { path: 'cms/settings',  name: 'cms.settings',  component: () => import('@/views/cms/SiteSettingsView.vue'),  meta: { permission: 'site_settings.view' } },

            // =====================================================
            // USER ACCESS
            // =====================================================

            { path: 'access/users', name: 'access.users', component: UserAccessList, meta: { permission: 'users.view' } },
            { path: 'access/users/:id/roles', name: 'access.users.edit', component: UserAccessList, meta: { permission: 'users.edit' } },
             { path: 'access/users/new', name: 'access.users.create', component: UserAccessList, meta: { permission: 'users.create' } },

            // =====================================================
            // 403 page
            // =====================================================

            { path: 'forbidden', name: 'forbidden', component: Forbidden },
        ],
    },

    {
        path: '/:pathMatch(.*)*',
        redirect: '/',
    },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
});

/** First page the signed-in user is allowed to open (used when they land somewhere they may not see). */
function firstAllowedRoute(access) {
    const home = router
        .getRoutes()
        .filter((r) => r.meta.permission && (r.name === 'dashboard' || String(r.name).endsWith('.index') || r.name === 'access.users'))
        .find((r) => access.canAny(...[].concat(r.meta.permission)));

    return home ? { name: home.name } : null;
}

router.beforeEach(async (to) => {
    const auth = useAuthStore();

    if (
        to.matched.some((r) => r.meta.requiresAuth) &&
        !auth.isAuthenticated
    ) {
        return {
            name: 'login',
            query: {
                redirect: to.fullPath,
            },
        };
    }

    if (to.meta.guest) {
        if (auth.isAuthenticated) return { name: 'dashboard' };

        // Signed out: forget the previous user's access so the next login starts clean.
        usePermissionStore().reset();
        return true;
    }

    // Permission check: runs after authentication. The API enforces the same rules on every request.
    const needs = to.meta.permission;
    if (!needs) return true;

    const access = usePermissionStore();
    try {
        await access.load();
    } catch {
        return true; // could not load: let the API decide (it answers 401/403 itself)
    }

    if (access.canAny(...[].concat(needs))) return true;

    // e.g. a user without "dashboard.view" logs in: send them to the first page they may open.
    if (to.name === 'dashboard') {
        const landing = firstAllowedRoute(access);
        if (landing) return landing;
    }

    return { name: 'forbidden', query: { from: to.fullPath } };
});

export default router;