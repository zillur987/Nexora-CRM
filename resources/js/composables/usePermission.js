import { usePermissionStore } from '@/stores/permissions';

/**
 * const { can, canAny, canAll } = usePermission();
 * can('products.edit')            // boolean, reactive inside templates and computed()
 * canAny('roles.create', 'roles.edit')
 *
 * Loads the signed-in user's permissions the first time it is used, so pages work
 * even if the router guard or the login hook has not loaded them yet.
 */
export function usePermission() {
    const store = usePermissionStore();

    if (!store.loaded) {
        store.load().catch(() => {
            /* not signed in or API unreachable: can() stays false and the API decides */
        });
    }

    return {
        can: (permission) => store.can(permission),
        canAny: (...permissions) => store.canAny(...permissions),
        canAll: (...permissions) => store.canAll(...permissions),
        isSuperAdmin: () => store.isSuperAdmin,
    };
}
