import { usePermissionStore } from '@/stores/permissions';

/**
 * const { can, canAny, canAll } = usePermission();
 * can('products.edit')            // boolean, reactive inside templates and computed()
 * canAny('roles.create', 'roles.edit')
 */
export function usePermission() {
    const store = usePermissionStore();

    return {
        can: (permission) => store.can(permission),
        canAny: (...permissions) => store.canAny(...permissions),
        canAll: (...permissions) => store.canAll(...permissions),
        isSuperAdmin: () => store.isSuperAdmin,
    };
}
