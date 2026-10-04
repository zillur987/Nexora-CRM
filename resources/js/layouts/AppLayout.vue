<script setup>
import { ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import Sidebar from '@/layouts/Sidebar.vue';
import Topbar from '@/layouts/Topbar.vue';

const auth = useAuthStore();
const router = useRouter();

const collapsed = ref(localStorage.getItem('crm.sidebar') === '1');
const mobileOpen = ref(false);
watch(collapsed, (v) => localStorage.setItem('crm.sidebar', v ? '1' : '0'));

async function logout() {
    await auth.logout();
    router.push({ name: 'login' });
}
</script>

<template>
    <div class="crm-shell" :class="{ 'is-collapsed': collapsed }">
        <Sidebar
            :collapsed="collapsed"
            :mobile-open="mobileOpen"
            :user="auth.user"
            @toggle="collapsed = !collapsed"
            @close="mobileOpen = false"
        />
        <div v-if="mobileOpen" class="crm-overlay" @click="mobileOpen = false"></div>

        <div class="crm-main">
            <Topbar :user="auth.user" @menu="mobileOpen = true" @logout="logout" />
            <main class="crm-content">
                <RouterView />
            </main>
        </div>
    </div>
</template>