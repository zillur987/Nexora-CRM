<script setup>
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';

const auth = useAuthStore();
const route = useRoute();
const router = useRouter();

const nav = [
    { label: 'Dashboard', icon: 'bi-speedometer2', to: { name: 'dashboard' }, match: (p) => p === '/' },
    { label: 'Contacts', icon: 'bi-person-lines-fill', to: { name: 'contacts.index' }, match: (p) => p.startsWith('/contacts') },
    { label: 'Deals', icon: 'bi-briefcase-fill', to: { name: 'deals.index' }, match: (p) => p.startsWith('/deals') },
];

async function logout() {
    await auth.logout();
    router.push({ name: 'login' });
}
</script>

<template>
    <div class="d-flex">
        <aside class="sidebar p-3 d-flex flex-column flex-shrink-0">
            <RouterLink :to="{ name: 'dashboard' }" class="text-white text-decoration-none fs-4 fw-semibold mb-4 px-2">
                <i class="bi bi-people-fill me-2"></i>CRM
            </RouterLink>

            <ul class="nav nav-pills flex-column gap-1">
                <li v-for="item in nav" :key="item.label">
                    <RouterLink :to="item.to" class="nav-link" :class="{ active: item.match(route.path) }">
                        <i class="bi me-2" :class="item.icon"></i>{{ item.label }}
                    </RouterLink>
                </li>
            </ul>

            <div class="mt-auto pt-4 border-top border-secondary">
                <div class="text-white-50 small px-2 mb-2 text-truncate">
                    <i class="bi bi-person-circle me-1"></i>{{ auth.user?.email }}
                </div>
                <button class="btn btn-outline-light btn-sm w-100" @click="logout">
                    <i class="bi bi-box-arrow-right me-1"></i>Log out
                </button>
            </div>
        </aside>

        <main class="flex-grow-1 p-4" style="min-width: 0">
            <RouterView />
        </main>
    </div>
</template>
