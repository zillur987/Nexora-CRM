import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import AppLayout from '@/layouts/AppLayout.vue';

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
            { path: '', name: 'dashboard', component: () => import('@/views/DashboardView.vue') },

            { path: 'contacts', name: 'contacts.index', component: () => import('@/views/contacts/ContactListView.vue') },
            { path: 'contacts/new', name: 'contacts.create', component: () => import('@/views/contacts/ContactFormView.vue') },
            { path: 'contacts/:id', name: 'contacts.show', component: () => import('@/views/contacts/ContactDetailView.vue'), props: true },
            { path: 'contacts/:id/edit', name: 'contacts.edit', component: () => import('@/views/contacts/ContactFormView.vue'), props: true },

            { path: 'deals', name: 'deals.index', component: () => import('@/views/deals/DealListView.vue') },
            { path: 'deals/new', name: 'deals.create', component: () => import('@/views/deals/DealFormView.vue') },
            { path: 'deals/:id', name: 'deals.show', component: () => import('@/views/deals/DealDetailView.vue'), props: true },
            { path: 'deals/:id/edit', name: 'deals.edit', component: () => import('@/views/deals/DealFormView.vue'), props: true },
        ],
    },
    { path: '/:pathMatch(.*)*', redirect: '/' },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
});

router.beforeEach((to) => {
    const auth = useAuthStore();

    if (to.matched.some((r) => r.meta.requiresAuth) && !auth.isAuthenticated) {
        return { name: 'login', query: { redirect: to.fullPath } };
    }
    if (to.meta.guest && auth.isAuthenticated) {
        return { name: 'dashboard' };
    }
});

export default router;
