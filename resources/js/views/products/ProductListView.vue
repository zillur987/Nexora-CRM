<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref, unref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { productsApi } from '@/api/products';
import { useColumnVisibility } from '@/composables/useColumnVisibility';
import { useListQuery } from '@/composables/useListQuery';
import { useToastStore } from '@/stores/toast';
import { downloadCsv } from '@/utils/csv';
import { formatDate } from '@/utils/format';
import { parseApiError } from '@/utils/errors';
import { formatMoney } from '@/utils/money';
import { PRODUCT_CSV_COLUMNS, productToCsvRow } from '@/utils/productCsv';
import { formatQuantity, PRODUCT_TYPES, STATUSES, statusBadge, typeMeta } from '@/utils/productOptions';
import ConfirmModal from '@/components/ConfirmModal.vue';
import DeleteConfirmModal from '@/components/DeleteConfirmModal.vue';
import LoadingBlock from '@/components/LoadingBlock.vue';
import Pagination from '@/components/Pagination.vue';
import RecordDrawer from '@/components/RecordDrawer.vue';
import ProductFormView from '@/views/products/ProductFormView.vue';

const toast = useToastStore();
const route = useRoute();
const router = useRouter();

/* -------------------------------------------------------------------------- */
/* Static config                                                              */
/* -------------------------------------------------------------------------- */

const DEFAULT_FILTERS = {
    search: '',
    product_type: '',
    category_id: '',
    brand: '',
    is_active: '',
    track_inventory: '',
    low_stock: '',
    sort: 'created_at',
    direction: 'desc',
};

const SORT_OPTIONS = [
    { value: 'created_at', label: 'Created' },
    { value: 'name', label: 'Product name' },
    { value: 'sku', label: 'SKU' },
    { value: 'sale_price', label: 'Sale price' },
    { value: 'cost_price', label: 'Cost price' },
    { value: 'quantity_on_hand', label: 'Stock' },
];

// Order here = order of table columns and of the Columns dropdown.
const COLUMNS = [
    { key: 'id', label: 'Id', default: true },
    { key: 'name', label: 'Product name', locked: true, default: true },
    { key: 'sku', label: 'SKU', default: true },
    { key: 'category', label: 'Category', default: true },
    { key: 'type', label: 'Type', default: true },
    { key: 'sale_price', label: 'Sale price', default: true },
    { key: 'cost_price', label: 'Cost price', default: true },
    { key: 'stock', label: 'Stock', default: true },
    { key: 'status', label: 'Status', default: true },
    { key: 'brand', label: 'Brand', default: false },
    { key: 'barcode', label: 'Barcode', default: false },
    { key: 'tax_rate', label: 'Tax rate', default: false },
    { key: 'created_at', label: 'Created', default: false },
];

const { visible: visibleColumns, shown: shownColumns } = useColumnVisibility('products.visibleColumns.v1', COLUMNS);

const isRight = (key) => key === 'sale_price' || key === 'cost_price';

/* -------------------------------------------------------------------------- */
/* Data                                                                       */
/* -------------------------------------------------------------------------- */

const { filters, items, meta, loading, error, load, goToPage, reset } = useListQuery(productsApi.list, {
    ...DEFAULT_FILTERS,
});

const summary = ref({ total: 0, active: 0, low_stock: 0, by_type: {}, totals: [] });
const categories = ref([]);
const brands = ref([]);

async function loadSummary() {
    try {
        summary.value = await productsApi.summary();
    } catch {
        /* tabs and KPIs just show no figures; the list itself reports its own errors */
    }
}

const refresh = () => Promise.all([load(), loadSummary()]); // reload rows + tab counts + KPIs

// Sequential row number shown in the "Id" column (1, 2, 3 ...), continuing across pages.
const rowOffset = computed(() => {
    const m = unref(meta);
    if (!m) return 0;
    if (Number.isInteger(m.from)) return m.from - 1;
    return ((m.current_page ?? 1) - 1) * (m.per_page ?? 0);
});

/* -------------------------------------------------------------------------- */
/* KPI strip: stock value per currency (amounts are never summed across currencies) */
/* -------------------------------------------------------------------------- */

const kpis = computed(() => {
    const s = summary.value;
    const totals = s.totals ?? [];
    const stockValues = totals.filter((t) => Number(t.stock_cost) > 0).map((t) => ({ currency: t.currency, text: formatMoney(t.stock_cost, t.currency, { compact: true }) }));

    return [
        { key: 'active', label: 'Active products', values: [{ currency: '', text: String(s.active ?? 0) }], hint: `${s.total ?? 0} in catalog` },
        { key: 'low', label: 'Low stock', values: [{ currency: '', text: String(s.low_stock ?? 0) }], hint: 'At or below reorder level' },
        { key: 'value', label: 'Inventory value', values: stockValues, hint: 'Quantity × cost price' },
    ];
});

/* -------------------------------------------------------------------------- */
/* Filter panel + columns dropdown                                            */
/* -------------------------------------------------------------------------- */

const filterOpen = ref(false);
const filterRef = ref(null);
const columnsOpen = ref(false);
const columnsRef = ref(null);

const PANEL_FILTERS = ['category_id', 'brand', 'is_active', 'track_inventory', 'low_stock'];

// Counts only what lives inside the panel (search and the type tabs have their own controls).
const activeFilterCount = computed(() => {
    const f = unref(filters);
    const fields = PANEL_FILTERS.filter((key) => f[key] !== '').length;
    const sorted = f.sort !== DEFAULT_FILTERS.sort || f.direction !== DEFAULT_FILTERS.direction;
    return fields + (sorted ? 1 : 0);
});

/* -------------------------------------------------------------------------- */
/* Row actions menu (teleported so the scrolling table can't clip it)         */
/* -------------------------------------------------------------------------- */

const menu = ref({ product: null, top: 0, right: 0 });

function closeMenu() {
    menu.value = { product: null, top: 0, right: 0 };
}

function toggleMenu(event, product) {
    if (menu.value.product?.id === product.id) return closeMenu();

    const rect = event.currentTarget.getBoundingClientRect();
    menu.value = { product, top: rect.bottom + 4, right: window.innerWidth - rect.right };
}

/* -------------------------------------------------------------------------- */
/* Delete                                                                     */
/* -------------------------------------------------------------------------- */

const toDelete = ref(null);
const deleting = ref(false);

function askDelete(product) {
    closeMenu();
    toDelete.value = product;
}

async function confirmDelete() {
    deleting.value = true;
    try {
        const wasLastRowOnPage = items.value.length === 1;
        const page = unref(meta)?.current_page ?? 1;

        await productsApi.remove(toDelete.value.id);
        toast.success('Product deleted.');
        toDelete.value = null;

        // Reloading re-numbers the rows; step back a page if this one is now empty.
        const reload = wasLastRowOnPage && page > 1 ? goToPage(page - 1) : load();
        await Promise.all([reload, loadSummary()]);
    } catch (e) {
        toast.error(parseApiError(e).message);
        toDelete.value = null;
    } finally {
        deleting.value = false;
    }
}

/* -------------------------------------------------------------------------- */
/* Export                                                                     */
/* -------------------------------------------------------------------------- */

const printPage = () => window.print();

// Exports the rows currently loaded (current page, current filters).
function exportCsv() {
    downloadCsv(
        `products-${new Date().toISOString().slice(0, 10)}.csv`,
        PRODUCT_CSV_COLUMNS,
        items.value.map(productToCsvRow),
    );
}

/* -------------------------------------------------------------------------- */
/* Create / edit drawer (driven by the route, so it is linkable and survives refresh) */
/* -------------------------------------------------------------------------- */

const drawer = reactive({ show: false, productId: null });
const formRef = ref(null);
const confirmDiscard = ref(false);

watch(
    () => [route.name, route.params.id],
    ([name, id]) => {
        drawer.show = name === 'products.create' || name === 'products.edit';
        if (drawer.show) drawer.productId = name === 'products.edit' ? String(id) : null;
    },
    { immediate: true },
);

function closeDrawer() {
    confirmDiscard.value = false;
    router.replace({ name: 'products.index', query: route.query });
}

/** Every user-initiated close (X, Esc, backdrop) goes through here. */
function requestClose() {
    if (formRef.value?.isDirty) {
        confirmDiscard.value = true;
        return;
    }
    closeDrawer();
}

// Refresh the table, then close the drawer unless "Save & add another" was used.
async function onProductSaved(saved, { another } = {}) {
    await Promise.all([refresh(), loadLookups()]);
    if (!another) closeDrawer();
}

async function loadLookups() {
    try {
        const lookups = await productsApi.lookups();
        categories.value = lookups.categories ?? [];
        brands.value = lookups.brands ?? [];
    } catch {
        /* the category / brand filters just stay empty */
    }
}

/* -------------------------------------------------------------------------- */
/* Global listeners                                                           */
/* -------------------------------------------------------------------------- */

function onDocumentClick(event) {
    const target = event.target;
    if (filterOpen.value && !filterRef.value?.contains(target)) filterOpen.value = false;
    if (columnsOpen.value && !columnsRef.value?.contains(target)) columnsOpen.value = false;
    if (menu.value.product && !target.closest?.('.row-menu, .row-menu-toggle')) closeMenu();
}

function onKeydown(event) {
    if (event.key !== 'Escape') return;
    filterOpen.value = false;
    columnsOpen.value = false;
    closeMenu();
}

onMounted(() => {
    document.addEventListener('click', onDocumentClick);
    document.addEventListener('keydown', onKeydown);
    window.addEventListener('resize', closeMenu);
    window.addEventListener('scroll', closeMenu, true);

    loadSummary();
    loadLookups();
});

onBeforeUnmount(() => {
    document.removeEventListener('click', onDocumentClick);
    document.removeEventListener('keydown', onKeydown);
    window.removeEventListener('resize', closeMenu);
    window.removeEventListener('scroll', closeMenu, true);
});
</script>

<template>
    <div class="card products-page rounded-3 overflow-visible">
        <!-- Header -->
        <div class="card-header bg-white d-flex justify-content-between align-items-center px-3 py-3 rounded-top-3">
            <h1 class="products-title mb-0">Products</h1>
            <RouterLink :to="{ name: 'products.create' }" class="btn btn-primary no-print">
                <i class="bi bi-plus-lg me-2"></i>Create Product
            </RouterLink>
        </div>

        <!-- Catalog KPIs -->
        <section class="products-kpis" aria-label="Catalog summary">
            <div v-for="k in kpis" :key="k.key" class="products-kpi">
                <span class="products-kpi__label">{{ k.label }}</span>
                <strong class="products-kpi__value">
                    <template v-if="k.values.length">
                        <span v-for="v in k.values" :key="v.currency" class="products-kpi__amount">{{ v.text }}</span>
                    </template>
                    <template v-else>—</template>
                </strong>
                <span class="products-kpi__hint">{{ k.hint }}</span>
            </div>
        </section>

        <!-- Type tabs -->
        <ul class="nav nav-pills flex-nowrap overflow-auto gap-1 px-3 pt-3 pb-3 no-print">
            <li class="nav-item">
                <button type="button" class="nav-link text-nowrap" :class="{ active: filters.product_type === '' }" @click="filters.product_type = ''">
                    All
                    <span class="badge rounded-pill ms-1" :class="filters.product_type === '' ? 'text-bg-light' : 'text-bg-secondary'">
                        {{ summary.total }}
                    </span>
                </button>
            </li>
            <li v-for="t in PRODUCT_TYPES" :key="t.value" class="nav-item">
                <button type="button" class="nav-link text-nowrap" :class="{ active: filters.product_type === t.value }" @click="filters.product_type = t.value">
                    {{ t.label }}
                    <span class="badge rounded-pill ms-1" :class="filters.product_type === t.value ? 'text-bg-light' : 'text-bg-secondary'">
                        {{ summary.by_type?.[t.value] ?? 0 }}
                    </span>
                </button>
            </li>
        </ul>

        <!-- Toolbar -->
        <div class="products-toolbar d-flex flex-wrap align-items-center gap-2 px-3 py-3 bg-light border-top border-bottom no-print">
            <form class="input-group products-search" role="search" @submit.prevent="load">
                <input v-model.trim="filters.search" type="search" class="form-control" placeholder="Search" aria-label="Search products" />
                <button type="submit" class="btn btn-primary" aria-label="Search">
                    <i class="bi bi-search"></i>
                </button>
            </form>

            <!-- Filter -->
            <div ref="filterRef" class="position-relative">
                <button
                    type="button"
                    class="btn btn-outline-secondary bg-white text-body"
                    aria-haspopup="dialog"
                    :aria-expanded="filterOpen"
                    @click="filterOpen = !filterOpen"
                >
                    <i class="bi bi-funnel me-2"></i>Filter
                    <span v-if="activeFilterCount" class="badge text-bg-primary ms-2">{{ activeFilterCount }}</span>
                    <i class="bi bi-plus-lg ms-3"></i>
                </button>

                <div v-if="filterOpen" class="card shadow filter-panel" role="dialog" aria-label="Filter products">
                    <div class="card-body d-flex flex-column gap-3">
                        <div>
                            <label for="product-filter-status" class="form-label small text-muted mb-1">Status</label>
                            <select id="product-filter-status" v-model="filters.is_active" class="form-select">
                                <option value="">All</option>
                                <option v-for="s in STATUSES" :key="s.value" :value="s.value">{{ s.label }}</option>
                            </select>
                        </div>

                        <div>
                            <label for="product-filter-category" class="form-label small text-muted mb-1">Category</label>
                            <select id="product-filter-category" v-model="filters.category_id" class="form-select">
                                <option value="">All</option>
                                <option v-for="c in categories" :key="c.id" :value="String(c.id)">{{ c.name }}</option>
                            </select>
                        </div>

                        <div>
                            <label for="product-filter-brand" class="form-label small text-muted mb-1">Brand</label>
                            <select id="product-filter-brand" v-model="filters.brand" class="form-select">
                                <option value="">All</option>
                                <option v-for="b in brands" :key="b" :value="b">{{ b }}</option>
                            </select>
                        </div>

                        <div class="row g-2">
                            <div class="col-6">
                                <label for="product-filter-tracking" class="form-label small text-muted mb-1">Inventory</label>
                                <select id="product-filter-tracking" v-model="filters.track_inventory" class="form-select">
                                    <option value="">Any</option>
                                    <option value="1">Tracked</option>
                                    <option value="0">Not tracked</option>
                                </select>
                            </div>
                            <div class="col-6">
                                <label for="product-filter-low" class="form-label small text-muted mb-1">Stock level</label>
                                <select id="product-filter-low" v-model="filters.low_stock" class="form-select">
                                    <option value="">Any</option>
                                    <option value="1">Low stock only</option>
                                </select>
                            </div>
                        </div>

                        <div class="row g-2">
                            <div class="col-7">
                                <label for="product-filter-sort" class="form-label small text-muted mb-1">Sort by</label>
                                <select id="product-filter-sort" v-model="filters.sort" class="form-select">
                                    <option v-for="o in SORT_OPTIONS" :key="o.value" :value="o.value">{{ o.label }}</option>
                                </select>
                            </div>
                            <div class="col-5">
                                <label for="product-filter-order" class="form-label small text-muted mb-1">Order</label>
                                <select id="product-filter-order" v-model="filters.direction" class="form-select">
                                    <option value="desc">Descending</option>
                                    <option value="asc">Ascending</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="card-footer bg-white d-flex justify-content-between align-items-center">
                        <button type="button" class="btn btn-link text-decoration-none px-0" @click="reset">
                            <i class="bi bi-x-lg me-1"></i>Reset all
                        </button>
                        <button type="button" class="btn btn-primary" @click="filterOpen = false">Done</button>
                    </div>
                </div>
            </div>

            <!-- Columns -->
            <div ref="columnsRef" class="position-relative">
                <button
                    type="button"
                    class="btn btn-outline-secondary bg-white text-body"
                    aria-haspopup="true"
                    :aria-expanded="columnsOpen"
                    @click="columnsOpen = !columnsOpen"
                >
                    Columns<i class="bi bi-chevron-down ms-4 small"></i>
                </button>

                <ul v-if="columnsOpen" class="dropdown-menu show p-2 shadow mt-1">
                    <li v-for="c in COLUMNS" :key="c.key">
                        <div class="form-check mx-2 my-1">
                            <input
                                :id="`product-col-${c.key}`"
                                v-model="visibleColumns"
                                type="checkbox"
                                class="form-check-input"
                                :value="c.key"
                                :disabled="c.locked"
                            />
                            <label :for="`product-col-${c.key}`" class="form-check-label w-100 text-nowrap">{{ c.label }}</label>
                        </div>
                    </li>
                </ul>
            </div>

            <!-- Export -->
            <div class="ms-auto d-flex gap-2">
                <button type="button" class="btn btn-light border shadow-sm" @click="printPage">
                    <i class="bi bi-printer me-2"></i>Print PDF
                </button>
                <button type="button" class="btn btn-light border shadow-sm" :disabled="!items.length" @click="exportCsv">
                    <i class="bi bi-filetype-csv me-2"></i>Download CSV
                </button>
            </div>
        </div>

        <!-- Table -->
        <div v-if="error" class="alert alert-danger m-3">{{ error }}</div>
        <LoadingBlock v-else-if="loading && !items.length" />

        <div v-else class="table-responsive products-scroll" :style="{ opacity: loading ? 0.6 : 1 }">
            <table class="table align-middle mb-0 products-table">
                <thead>
                    <tr>
                        <th v-for="c in shownColumns" :key="c.key" scope="col" :class="{ 'text-end': isRight(c.key) }">{{ c.label }}</th>
                        <th scope="col" class="no-print"><span class="visually-hidden">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="!items.length">
                        <td :colspan="shownColumns.length + 1" class="text-center text-muted py-5">No products found.</td>
                    </tr>

                    <tr v-for="(p, index) in items" :key="p.id">
                        <td v-for="col in shownColumns" :key="col.key" :class="{ 'text-end': isRight(col.key) }">
                            <template v-if="col.key === 'id'">{{ rowOffset + index + 1 }}</template>

                            <RouterLink v-else-if="col.key === 'name'" :to="{ name: 'products.show', params: { id: p.id } }" class="product-link">
                                {{ p.name }}
                            </RouterLink>

                            <template v-else-if="col.key === 'sku'">{{ p.sku }}</template>
                            <template v-else-if="col.key === 'category'">{{ p.category?.name ?? '—' }}</template>

                            <span v-else-if="col.key === 'type'">
                                <span v-if="typeMeta(p.product_type)" class="badge" :class="typeMeta(p.product_type).badge">{{ typeMeta(p.product_type).label }}</span>
                                <template v-else>—</template>
                            </span>

                            <template v-else-if="col.key === 'sale_price'">{{ formatMoney(p.sale_price, p.currency) }}</template>
                            <template v-else-if="col.key === 'cost_price'">{{ formatMoney(p.cost_price, p.currency) }}</template>

                            <span v-else-if="col.key === 'stock'">
                                <template v-if="p.track_inventory">
                                    <span :class="{ 'text-danger': p.is_low_stock }">{{ formatQuantity(p.quantity_on_hand, p.unit_of_measure) }}</span>
                                    <span v-if="p.is_low_stock" class="badge text-bg-danger ms-2">Low</span>
                                </template>
                                <span v-else class="text-muted">Not tracked</span>
                            </span>

                            <span v-else-if="col.key === 'status'" class="badge" :class="statusBadge(p.is_active)">{{ p.is_active ? 'Active' : 'Inactive' }}</span>

                            <template v-else-if="col.key === 'brand'">{{ p.brand ?? '—' }}</template>
                            <template v-else-if="col.key === 'barcode'">{{ p.barcode ?? '—' }}</template>
                            <template v-else-if="col.key === 'tax_rate'">{{ p.tax_rate === null ? '—' : `${Number(p.tax_rate)}%` }}</template>
                            <template v-else-if="col.key === 'created_at'">{{ formatDate(p.created_at) }}</template>
                        </td>

                        <td class="text-end no-print">
                            <button
                                type="button"
                                class="btn btn-sm btn-link text-body row-menu-toggle"
                                aria-haspopup="menu"
                                :aria-expanded="menu.product?.id === p.id"
                                :aria-label="`Actions for ${p.name}`"
                                @click="toggleMenu($event, p)"
                            >
                                <i class="bi bi-three-dots"></i>
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="meta" class="card-footer bg-white no-print">
            <Pagination :meta="meta" @change="goToPage" />
        </div>
    </div>

    <!-- Create / edit drawer -->
    <RecordDrawer :show="drawer.show" :title="drawer.productId ? 'Edit Product' : 'Create Product'" @close="requestClose">
        <ProductFormView
            ref="formRef"
            :key="route.fullPath"
            :product-id="drawer.productId"
            :categories="categories"
            :brands="brands"
            @saved="onProductSaved"
            @cancel="closeDrawer"
        />
    </RecordDrawer>

    <!-- Row actions menu -->
    <Teleport to="body">
        <ul v-if="menu.product" class="dropdown-menu show row-menu shadow" role="menu" :style="{ top: `${menu.top}px`, right: `${menu.right}px` }">
            <li>
                <RouterLink class="dropdown-item" role="menuitem" :to="{ name: 'products.show', params: { id: menu.product.id } }" @click="closeMenu">
                    <i class="bi bi-eye me-2"></i>View
                </RouterLink>
            </li>
            <li>
                <RouterLink class="dropdown-item" role="menuitem" :to="{ name: 'products.edit', params: { id: menu.product.id } }" @click="closeMenu">
                    <i class="bi bi-pencil me-2"></i>Edit
                </RouterLink>
            </li>
            <li><hr class="dropdown-divider" /></li>
            <li>
                <button type="button" class="dropdown-item text-danger" role="menuitem" @click="askDelete(menu.product)">
                    <i class="bi bi-trash me-2"></i>Delete
                </button>
            </li>
        </ul>
    </Teleport>

    <DeleteConfirmModal
        :show="!!toDelete"
        title="Delete product"
        :message="`Delete ${toDelete?.name}?`"
        :loading="deleting"
        @confirm="confirmDelete"
        @cancel="toDelete = null"
    />

    <ConfirmModal
        :show="confirmDiscard"
        title="Discard changes?"
        message="You have unsaved changes. If you close now, they will be lost."
        confirm-text="Discard"
        @confirm="closeDrawer"
        @cancel="confirmDiscard = false"
    />
</template>

<style scoped>
/* All sizes, colours and fonts come from the global --crm-* tokens (resources/css/layout.css),
   so changing a token restyles Leads, Contacts, Companies, Deals and every future module together. */
.products-page {
    font-family: var(--crm-font);
    font-size: var(--crm-fs);
    line-height: 1.5;
    color: var(--crm-text);
}

.products-title {
    font-size: 1.5rem;
    font-weight: 600;
    letter-spacing: -0.02em;
}

/* Controls: one height, one font size, one radius */
.products-page .form-control,
.products-page .form-select {
    min-height: var(--crm-control-h);
    padding-block: 0.375rem;
    font-size: var(--crm-fs);
}

.products-page .form-label {
    margin-bottom: 0.25rem;
    font-size: var(--crm-fs-sm);
    font-weight: 500;
    color: var(--crm-text-muted);
}

.products-page .btn:not(.btn-sm) {
    --bs-btn-font-size: var(--crm-fs);
    --bs-btn-font-weight: 500;
    --bs-btn-padding-x: 0.875rem;
    --bs-btn-border-radius: var(--crm-radius);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: var(--crm-control-h);
}

.products-page .nav-link {
    padding: 0.4rem 0.875rem;
    font-size: var(--crm-fs);
    font-weight: 500;
}

.products-page .badge {
    font-size: var(--crm-fs-xs);
    font-weight: 500;
}

.products-page .dropdown-menu,
.row-menu {
    --bs-dropdown-font-size: var(--crm-fs);
    --bs-dropdown-border-radius: var(--crm-radius);
    --bs-dropdown-item-padding-y: 0.4rem;
}

.products-page :deep(.pagination) {
    --bs-pagination-font-size: var(--crm-fs);
}

/* Catalog KPIs */
.products-kpis {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(12rem, 1fr));
    border-bottom: 1px solid var(--crm-border);
}

.products-kpi {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    padding: 1rem 1.25rem;
}

.products-kpi + .products-kpi {
    border-left: 1px solid var(--crm-border);
}

.products-kpi__label {
    font-size: var(--crm-fs-sm);
    font-weight: 500;
    color: var(--crm-text-muted);
}

.products-kpi__value {
    display: flex;
    flex-wrap: wrap;
    gap: 0 0.75rem;
    font-size: 1.125rem;
    font-weight: 600;
    font-variant-numeric: tabular-nums;
    line-height: 1.4;
}

.products-kpi__hint {
    font-size: var(--crm-fs-xs);
    color: var(--crm-text-muted);
}

/* Toolbar */
.products-search {
    flex: 0 1 340px;
    min-width: 220px;
}

.filter-panel {
    position: absolute;
    top: calc(100% + 0.5rem);
    left: 0;
    z-index: 1050;
    width: 22rem;
    max-width: calc(100vw - 2rem);
}

/* Table: plain, single-line rows with thin dividers */
.products-table {
    --bs-table-bg: transparent;
    font-size: var(--crm-fs);
    color: var(--crm-text);
    font-variant-numeric: tabular-nums;
}

.products-table thead th {
    position: sticky;
    top: 0;
    z-index: 1;
    padding: 0.75rem 1rem;
    font-size: var(--crm-fs-sm);
    font-weight: 600;
    color: var(--crm-text-strong);
    white-space: nowrap;
    background: var(--crm-surface);
    border-bottom: 0;
    /* A shadow instead of a border, so the divider stays visible on the sticky header. */
    box-shadow: inset 0 -1px 0 var(--crm-border);
}

.products-table tbody td {
    padding: 0.75rem 1rem;
    white-space: nowrap;
    border-bottom: 1px solid var(--crm-border);
}

.products-table tbody tr:last-child td {
    border-bottom: 0;
}

.product-link {
    color: inherit;
    text-decoration: none;
}

.product-link:hover {
    color: var(--bs-primary);
    text-decoration: underline;
}

/* Row actions menu */
.row-menu {
    position: fixed;
    left: auto;
    z-index: 1060;
    min-width: 9rem;
    margin: 0;
    font-family: var(--crm-font);
}

@media (max-width: 575.98px) {
    .products-kpi + .products-kpi {
        border-top: 1px solid var(--crm-border);
        border-left: 0;
    }
}

@media print {
    .no-print {
        display: none !important;
    }

    .products-page {
        border: 0 !important;
        box-shadow: none !important;
    }

    .products-page .table-responsive {
        overflow: visible !important;
        max-height: none !important;
    }
}
</style>
