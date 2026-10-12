import { defineStore } from 'pinia';
import { accessApi } from '@/api/access';

/**
 * The signed-in user's roles and permissions.
 * Load once after login (or let the router guard do it) and call reset() on logout.
 * This only decides what the UI shows; the API enforces the same rules on every request.
 */
export const usePermissionStore = defineStore('permissions', {
    state: () => ({
        loaded: false,
        loading: null,
        isSuperAdmin: false,
        roles: [],
        permissions: [],
    }),

    getters: {
        permissionSet: (state) => new Set(state.permissions),
    },

    actions: {
        async load(force = false) {
            if (this.loaded && !force) return;
            if (!this.loading) {
                this.loading = accessApi
                    .me()
                    .then((data) => {
                        this.isSuperAdmin = Boolean(data.is_super_admin);
                        this.roles = data.roles ?? [];
                        this.permissions = data.permissions ?? [];
                        this.loaded = true;
                    })
                    .finally(() => {
                        this.loading = null;
                    });
            }
            return this.loading;
        },

        can(permission) {
            return this.isSuperAdmin || this.permissionSet.has(permission);
        },

        canAny(...permissions) {
            return permissions.flat().some((p) => this.can(p));
        },

        canAll(...permissions) {
            return permissions.flat().every((p) => this.can(p));
        },

        reset() {
            this.$reset();
        },
    },
});
