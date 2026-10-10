import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import AppLayout from '@/layouts/AppLayout.vue';

const LeadList = () => import('@/views/leads/LeadListView.vue');
const CompanyList = () => import('@/views/companies/CompanyListView.vue');
const ContactList = () => import('@/views/contacts/ContactListView.vue');
const DealList = () => import('@/views/deals/DealListView.vue');

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