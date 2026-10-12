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
                component: () => import('@/views/DashboardView.vue'),
            },

            // =====================================================
            // LEADS
            // =====================================================

            {
                path: 'leads',
                name: 'leads.index',
                component: LeadList,
            },

            {
                path: 'leads/new',
                name: 'leads.create',
                component: LeadList,
            },

            {
                path: 'leads/:id',
                name: 'leads.show',
                component: () =>
                    import('@/views/leads/LeadDetailView.vue'),
                props: true,
            },

            {
                path: 'leads/:id/edit',
                name: 'leads.edit',
                component: LeadList,
            },

            // =====================================================
            // COMPANIES
            // =====================================================

            {
                path: 'companies',
                name: 'companies.index',
                component: CompanyList,
            },

            {
                // Create / edit render the list with the drawer open (same pattern as leads).
                path: 'companies/new',
                name: 'companies.create',
                component: CompanyList,
            },

            {
                path: 'companies/:id',
                name: 'companies.show',
                component: () =>
                    import('@/views/companies/CompanyDetailView.vue'),
                props: true,
            },

            {
                path: 'companies/:id/edit',
                name: 'companies.edit',
                component: CompanyList,
            },

            // =====================================================
            // CONTACTS
            // =====================================================

            {
                path: 'contacts',
                name: 'contacts.index',
                component: ContactList,
            },

            {
                path: 'contacts/new',
                name: 'contacts.create',
                component: ContactList,
            },

            {
                path: 'contacts/:id',
                name: 'contacts.show',
                component: () => import('@/views/contacts/ContactDetailView.vue'),
                props: true,
            },

            {
                path: 'contacts/:id/edit',
                name: 'contacts.edit',
                component: ContactList,
            },


            // =====================================================
            // DEALS
            // =====================================================

            {
                path: 'deals',
                name: 'deals.index',
                component: DealList,
            },

            {
                path: 'deals/new',
                name: 'deals.create',
                component: DealList,
            },

            {
                path: 'deals/:id',
                name: 'deals.show',
                component: () => import('@/views/deals/DealDetailView.vue'),
                props: true,
            },

            {
                path: 'deals/:id/edit',
                name: 'deals.edit',
                component: DealList,
            },
            // =====================================================
            // PIPELINES
            // =====================================================

            {
                path: 'pipelines',
                name: 'pipelines.index',
                component: () => import('@/views/pipelines/PipelineListView.vue'),
            },

            // =====================================================
            // PRODUCTS
            // =====================================================

            {
                path: 'products',
                name: 'products.index',
                component: ProductList,
            },

            {
                path: 'products/new',
                name: 'products.create',
                component: ProductList,
            },

            {
                path: 'products/:id',
                name: 'products.show',
                component: () => import('@/views/products/ProductDetailView.vue'),
                props: true,
            },

            {
                path: 'products/:id/edit',
                name: 'products.edit',
                component: ProductList,
            },

            // =====================================================
            // QUOTES
            // =====================================================

            {
                path: 'quotes',
                name: 'quotes.index',
                component: QuoteList,
            },

            {
                path: 'quotes/new',
                name: 'quotes.create',
                component: QuoteList,
            },

            {
                path: 'quotes/:id',
                name: 'quotes.show',
                component: () => import('@/views/quotes/QuoteDetailView.vue'),
                props: true,
            },

            {
                path: 'quotes/:id/edit',
                name: 'quotes.edit',
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

            // =====================================================
            // USER ACCESS
            // =====================================================

            { path: 'access/users', name: 'access.users', component: UserAccessList, meta: { permission: 'users.view' } },
            { path: 'access/users/:id/roles', name: 'access.users.edit', component: UserAccessList, meta: { permission: 'users.edit' } },

            // =====================================================
            // 403 page
            // =====================================================

            { path: 'forbidden', name: 'forbidden', component: Forbidden },

            // 3) Also tag the existing module routes, e.g. products:
            //      { path: 'products', name: 'products.index', component: ProductList, meta: { permission: 'products.view' } },
            //      { path: 'products/new', ..., meta: { permission: 'products.create' } },
            //      { path: 'products/:id', ..., meta: { permission: 'products.view' } },
            //      { path: 'products/:id/edit', ..., meta: { permission: 'products.edit' } },

            // 4) Global guard: place AFTER your existing authentication guard.
            //
            // router.beforeEach(async (to) => {
            //     const needs = to.meta.permission;
            //     if (!needs) return true;
            //
            //     const access = usePermissionStore();
            //     try {
            //         await access.load();
            //     } catch {
            //         return true; // could not load: let the API decide (it returns 401/403 itself)
            //     }
            //
            //     return access.canAny(...[].concat(needs)) ? true : { name: 'forbidden', query: { from: to.fullPath } };
            // });



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

router.beforeEach((to) => {
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

    if (to.meta.guest && auth.isAuthenticated) {
        return {
            name: 'dashboard',
        };
    }
});

export default router;