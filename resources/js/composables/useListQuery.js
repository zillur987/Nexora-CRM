import { onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';

const clean = (params) =>
    Object.fromEntries(
        Object.entries(params).filter(([, v]) => v !== '' && v !== null && v !== undefined),
    );

/**
 * Paginated + filterable list state, kept in sync with the URL query string
 * (so filters survive refresh / can be shared) and safe against out-of-order responses.
 *
 * @param {(params: object) => Promise<{data: any[], meta: object}>} fetcher
 * @param {object} defaults  filter defaults, e.g. { search: '', status: '' }
 */
export function useListQuery(fetcher, defaults) {
    const route = useRoute();
    const router = useRouter();

    const filters = reactive({ ...defaults });
    for (const key of Object.keys(defaults)) {
        if (route.query[key] !== undefined) filters[key] = String(route.query[key]);
    }

    const page = ref(Number(route.query.page) || 1);
    const items = ref([]);
    const meta = ref(null);
    const loading = ref(true); // true from the start so the list never flashes "empty"
    const error = ref(null);

    let timer = null;
    let latest = 0;

    async function load() {
        const requestId = ++latest;
        loading.value = true;
        error.value = null;

        try {
            const res = await fetcher(clean({ ...filters, page: page.value }));
            if (requestId !== latest) return; // a newer request superseded this one
            items.value = res.data;
            meta.value = res.meta;
            router.replace({ query: clean({ ...filters, page: page.value > 1 ? page.value : '' }) });
        } catch {
            if (requestId === latest) error.value = 'Failed to load data.';
        } finally {
            if (requestId === latest) loading.value = false;
        }
    }

    function goToPage(next) {
        page.value = next;
        load();
    }

    function reset() {
        Object.assign(filters, defaults);
    }

    watch(filters, () => {
        clearTimeout(timer);
        timer = setTimeout(() => {
            page.value = 1;
            load();
        }, 300);
    });

    onMounted(load);
    onBeforeUnmount(() => clearTimeout(timer));

    return { filters, items, meta, loading, error, load, goToPage, reset };
}
