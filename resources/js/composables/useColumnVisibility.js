import { computed, ref, watch } from 'vue';

/**
 * Which table columns are shown, persisted per browser.
 *
 * @param {string} storageKey  bump the suffix (".v2") when the column set changes
 * @param {Array<{key: string, locked?: boolean, default?: boolean}>} columns  in table order
 */
export function useColumnVisibility(storageKey, columns) {
    const known = columns.map((c) => c.key);
    const locked = columns.filter((c) => c.locked).map((c) => c.key);

    function restore() {
        try {
            const saved = JSON.parse(localStorage.getItem(storageKey));
            if (Array.isArray(saved)) return [...new Set([...locked, ...saved.filter((key) => known.includes(key))])];
        } catch {
            /* fall through to the defaults */
        }
        return columns.filter((c) => c.default).map((c) => c.key);
    }

    const visible = ref(restore());

    watch(
        visible,
        (value) => {
            try {
                localStorage.setItem(storageKey, JSON.stringify(value));
            } catch {
                /* storage unavailable: keep working without persistence */
            }
        },
        { deep: true },
    );

    const shown = computed(() => columns.filter((c) => visible.value.includes(c.key)));
    const isVisible = (key) => visible.value.includes(key);

    return { visible, shown, isVisible };
}
