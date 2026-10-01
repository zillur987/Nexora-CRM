import { defineStore } from 'pinia';
import { authApi } from '@/api/auth';
import { TOKEN_KEY } from '@/api/http';

export const useAuthStore = defineStore('auth', {
    state: () => ({
        user: null,
        token: localStorage.getItem(TOKEN_KEY),
    }),

    getters: {
        isAuthenticated: (state) => Boolean(state.token && state.user),
    },

    actions: {
        async bootstrap() {
            if (!this.token) return;
            try {
                this.user = await authApi.me();
            } catch {
                this.clear();
            }
        },

        async login(credentials) {
            const { token, user } = await authApi.login(credentials);
            this.token = token;
            this.user = user;
            localStorage.setItem(TOKEN_KEY, token);
        },

        async logout() {
            try {
                await authApi.logout();
            } catch {
                /* token may already be invalid – ignore */
            }
            this.clear();
        },

        clear() {
            this.token = null;
            this.user = null;
            localStorage.removeItem(TOKEN_KEY);
        },
    },
});
