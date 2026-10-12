import { watch } from 'vue';
import { usePermissionStore } from '@/stores/permissions';

/**
 * Hides an element unless the user holds the permission.
 *
 *   <button v-can="'products.create'">New</button>
 *   <button v-can="['roles.edit', 'roles.create']">Any of these</button>
 *   <button v-can.all="['products.edit', 'products.delete']">All of these</button>
 *   <button v-can.disable="'products.edit'">Disabled instead of hidden</button>
 *
 * It re-evaluates when the permission store changes, e.g. after the access list loads.
 */
function allowed(store, binding) {
    const needs = [].concat(binding.value ?? []);
    if (!needs.length) return true;
    return binding.modifiers.all ? store.canAll(...needs) : store.canAny(...needs);
}

function apply(el, ok, binding) {
    if (binding.modifiers.disable) {
        el.toggleAttribute('disabled', !ok);
        el.classList.toggle('disabled', !ok);
        if (!ok) el.setAttribute('aria-disabled', 'true');
        else el.removeAttribute('aria-disabled');
        return;
    }

    if (el.__canDisplay === undefined) el.__canDisplay = el.style.display;
    el.style.display = ok ? el.__canDisplay : 'none';
}

export const canDirective = {
    mounted(el, binding) {
        const store = usePermissionStore();
        el.__canBinding = binding;
        el.__canStop = watch(
            () => allowed(store, el.__canBinding),
            (ok) => apply(el, ok, el.__canBinding),
            { immediate: true },
        );
    },
    updated(el, binding) {
        el.__canBinding = binding;
        apply(el, allowed(usePermissionStore(), binding), binding);
    },
    unmounted(el) {
        el.__canStop?.();
    },
};

// main.js:  import { canDirective } from '@/directives/can';  app.directive('can', canDirective);
