import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import AppLayout from '@/layouts/AppLayout.vue';

const LeadList = () => import('@/views/leads/LeadListView.vue');

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
            // CONTACTS
            // =====================================================

            {
                path: 'contacts',
                name: 'contacts.index',
                component: () =>
                    import('@/views/contacts/ContactListView.vue'),
            },

            {
                path: 'contacts/new',
                name: 'contacts.create',
                component: () =>
                    import('@/views/contacts/ContactFormView.vue'),
            },

            {
                path: 'contacts/:id',
                name: 'contacts.show',
                component: () =>
                    import('@/views/contacts/ContactDetailView.vue'),
                props: true,
            },

            {
                path: 'contacts/:id/edit',
                name: 'contacts.edit',
                component: () =>
                    import('@/views/contacts/ContactFormView.vue'),
                props: true,
            },

            // =====================================================
            // DEALS
            // =====================================================

            {
                path: 'deals',
                name: 'deals.index',
                component: () =>
                    import('@/views/deals/DealListView.vue'),
            },

            {
                path: 'deals/new',
                name: 'deals.create',
                component: () =>
                    import('@/views/deals/DealFormView.vue'),
            },

            {
                path: 'deals/:id',
                name: 'deals.show',
                component: () =>
                    import('@/views/deals/DealDetailView.vue'),
                props: true,
            },

            {
                path: 'deals/:id/edit',
                name: 'deals.edit',
                component: () =>
                    import('@/views/deals/DealFormView.vue'),
                props: true,
            },

            // =====================================================
            // PIPELINES
            // =====================================================

            {
                path: 'pipelines',
                name: 'pipelines.index',
                component: () =>
                    import('@/views/pipelines/PipelineListView.vue'),
            },

            {
                path: 'pipelines/new',
                name: 'pipelines.create',
                component: () =>
                    import('@/views/pipelines/PipelineFormView.vue'),
            },

            {
                path: 'pipelines/:id/edit',
                name: 'pipelines.edit',
                component: () =>
                    import('@/views/pipelines/PipelineFormView.vue'),
                props: true,
            },

            {
                path: 'pipelines/:id/board',
                name: 'pipelines.board',
                component: () =>
                    import('@/views/pipelines/PipelineBoardView.vue'),
                props: true,
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