import { createApp } from 'vue';
import { createPinia } from 'pinia';
import App from '@/App.vue';
import router from '@/router';
import { useAuthStore } from '@/stores/auth';
import 'bootstrap-icons/font/bootstrap-icons.css'
import '../css/layout.css';

const pinia = createPinia();
const app = createApp(App).use(pinia);
const auth = useAuthStore(pinia);

// An expired/invalid token anywhere in the app sends the user back to the login page.
window.addEventListener('auth:expired', () => {
    auth.clear();
    if (router.currentRoute.value.name !== 'login') {
        router.push({ name: 'login', query: { redirect: router.currentRoute.value.fullPath } });
    }
});

// Resolve the current user (if a token exists) BEFORE the first navigation so route guards are accurate.
auth.bootstrap().finally(() => {
    app.use(router).mount('#app');
});
