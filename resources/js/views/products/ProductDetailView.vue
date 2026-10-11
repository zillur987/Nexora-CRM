<script setup>
import { computed, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import { productsApi } from '@/api/products';
import { useToastStore } from '@/stores/toast';
import { parseApiError } from '@/utils/errors';
import { formatDate } from '@/utils/format';
import { formatMoney } from '@/utils/money';
import { formatQuantity, marginPercent, statusBadge, typeMeta } from '@/utils/productOptions';
import DeleteConfirmModal from '@/components/DeleteConfirmModal.vue';
import LoadingBlock from '@/components/LoadingBlock.vue';

const props = defineProps({ id: { type: String, required: true } });

const router = useRouter();
const toast = useToastStore();

/* -------------------------------------------------------------------------- */
/* State                                                                      */
/* -------------------------------------------------------------------------- */

const product = ref(null);
const busy = ref(false);
const confirmingDelete = ref(false);

watch(
    () => props.id,
    async (id) => {
        product.value = null;
        try {
            product.value = await productsApi.get(id);
        } catch {
            toast.error('Product not found.');
            router.replace({ name: 'products.index' });
        }
    },
    { immediate: true },
);

/* -------------------------------------------------------------------------- */
/* Presentation (all derived from `product`, so the template stays declarative) */
/* -------------------------------------------------------------------------- */

const dash = (value) => value || '—';
const date = (value) => (value ? formatDate(value, true) : '—');

const type = computed(() => typeMeta(product.value.product_type));

const subtitle = computed(() => {
    const { sku, brand, category } = product.value;
    return [sku, brand, category?.name].filter(Boolean).join(' · ');
});

const margin = computed(() => marginPercent(product.value.sale_price, product.value.cost_price));

// Add a row here and it renders; no template changes needed.
const factCells = computed(() => {
    const p = product.value;
    return [
        { key: 'sale', label: 'Sale price', value: formatMoney(p.sale_price, p.currency) },
        { key: 'cost', label: 'Cost price', value: formatMoney(p.cost_price, p.currency) },
        { key: 'margin', label: 'Margin', value: margin.value === null ? '—' : `${margin.value.toFixed(1)}%` },
        {
            key: 'stock',
            label: 'In stock',
            value: p.track_inventory ? formatQuantity(p.quantity_on_hand, p.unit_of_measure) : 'Not tracked',
            danger: p.is_low_stock,
        },
    ];
});

const detailRows = computed(() => {
    const p = product.value;
    return [
        { key: 'sku', label: 'SKU', value: dash(p.sku) },
        { key: 'barcode', label: 'Barcode', value: dash(p.barcode) },
        { key: 'type', label: 'Product type', value: dash(type.value?.label) },
        { key: 'category', label: 'Category', value: dash(p.category?.name) },
        { key: 'brand', label: 'Brand', value: dash(p.brand) },
        { key: 'unit', label: 'Unit of measure', value: dash(p.unit_of_measure) },
        { key: 'currency', label: 'Currency', value: dash(p.currency) },
        { key: 'tax', label: 'Tax rate', value: p.tax_rate === null ? '—' : `${Number(p.tax_rate)}%` },
    ];
});

const inventoryRows = computed(() => {
    const p = product.value;
    if (!p.track_inventory) return [];
    return [
        { key: 'on_hand', label: 'Quantity on hand', value: formatQuantity(p.quantity_on_hand, p.unit_of_measure), danger: p.is_low_stock },
        { key: 'reorder', label: 'Reorder level', value: formatQuantity(p.reorder_level, p.unit_of_measure) },
    ];
});

const shippingRows = computed(() => {
    const p = product.value;
    const d = p.dimensions;
    const hasDimensions = d && (d.length || d.width || d.height);

    return [
        { key: 'weight', label: 'Weight', value: p.weight === null ? '—' : `${Number(p.weight)} ${p.weight_unit ?? ''}`.trim() },
        { key: 'dimensions', label: 'Dimensions (L × W × H)', value: hasDimensions ? `${d.length ?? '—'} × ${d.width ?? '—'} × ${d.height ?? '—'} ${d.unit ?? ''}`.trim() : '—' },
    ];
});

const linkRows = computed(() => {
    const p = product.value;
    return [{ key: 'website', icon: 'bi-globe', label: 'Website', text: p.website_url, href: p.website_url }];
});

const activityRows = computed(() => [
    { key: 'created', label: 'Created', value: date(product.value.created_at) },
    { key: 'updated', label: 'Last updated', value: date(product.value.updated_at) },
]);

/* -------------------------------------------------------------------------- */
/* Actions                                                                    */
/* -------------------------------------------------------------------------- */

async function remove() {
    busy.value = true;
    try {
        await productsApi.remove(props.id);
        toast.success('Product deleted.');
        router.push({ name: 'products.index' });
    } catch (e) {
        toast.error(parseApiError(e).message);
        confirmingDelete.value = false;
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <LoadingBlock v-if="!product" />

    <div v-else class="product-view">
        <!-- Breadcrumb -->
        <nav class="product-view__crumbs" aria-label="Breadcrumb">
            <RouterLink :to="{ name: 'products.index' }">Products</RouterLink>
            <i class="bi bi-chevron-right" aria-hidden="true"></i>
            <span aria-current="page">{{ product.name }}</span>
        </nav>

        <!-- Header -->
        <header class="product-view__header">
            <div class="product-view__identity">
                <div class="avatar" aria-hidden="true">
                    <img v-if="product.image_url" :src="product.image_url" alt="" loading="lazy" />
                    <i v-else class="bi bi-box-seam"></i>
                </div>
                <div class="min-w-0">
                    <div class="d-flex align-items-center flex-wrap gap-2">
                        <h1 class="product-view__name">{{ product.name }}</h1>
                        <span v-if="type" class="badge" :class="type.badge">{{ type.label }}</span>
                        <span class="badge" :class="statusBadge(product.is_active)">{{ product.is_active ? 'Active' : 'Inactive' }}</span>
                        <span v-if="product.is_low_stock" class="badge text-bg-danger">Low stock</span>
                    </div>
                    <p class="product-view__subtitle">{{ subtitle || 'No category or brand' }}</p>
                </div>
            </div>

            <div class="product-view__actions">
                <RouterLink :to="{ name: 'products.edit', params: { id } }" class="btn btn-outline-secondary">
                    <i class="bi bi-pencil me-2"></i>Edit
                </RouterLink>
                <button type="button" class="btn btn-outline-danger" :disabled="busy" @click="confirmingDelete = true">
                    <i class="bi bi-trash me-2"></i>Delete
                </button>
            </div>
        </header>

        <!-- Key facts -->
        <section class="panel facts" aria-label="Key facts">
            <div v-for="cell in factCells" :key="cell.key" class="facts__cell">
                <span class="field-label">{{ cell.label }}</span>
                <strong class="facts__value" :class="{ 'text-danger': cell.danger }">{{ cell.value }}</strong>
            </div>
        </section>

        <!-- Body -->
        <div class="product-view__grid">
            <main class="product-view__main">
                <section class="panel">
                    <header class="panel__head"><h2 class="panel__title">Product information</h2></header>
                    <dl class="info">
                        <div v-for="row in detailRows" :key="row.key" class="info__item">
                            <dt class="field-label">{{ row.label }}</dt>
                            <dd class="info__value">{{ row.value }}</dd>
                        </div>
                    </dl>
                </section>

                <section class="panel">
                    <header class="panel__head"><h2 class="panel__title">Inventory &amp; shipping</h2></header>
                    <dl class="info">
                        <div v-if="!product.track_inventory" class="info__item">
                            <dt class="field-label">Inventory</dt>
                            <dd class="info__value">Not tracked</dd>
                        </div>
                        <div v-for="row in inventoryRows" :key="row.key" class="info__item">
                            <dt class="field-label">{{ row.label }}</dt>
                            <dd class="info__value" :class="{ 'text-danger': row.danger }">{{ row.value }}</dd>
                        </div>
                        <div v-for="row in shippingRows" :key="row.key" class="info__item">
                            <dt class="field-label">{{ row.label }}</dt>
                            <dd class="info__value">{{ row.value }}</dd>
                        </div>
                    </dl>
                </section>

                <section class="panel">
                    <header class="panel__head"><h2 class="panel__title">Description</h2></header>
                    <div class="panel__body">
                        <p v-if="product.short_description" class="notes notes--lead">{{ product.short_description }}</p>
                        <p v-if="product.description" class="notes">{{ product.description }}</p>
                        <p v-if="!product.short_description && !product.description" class="empty">No description yet. Add context by editing this product.</p>
                    </div>
                </section>
            </main>

            <aside class="product-view__side">
                <section class="panel">
                    <header class="panel__head"><h2 class="panel__title">Links</h2></header>
                    <ul class="rows">
                        <li v-for="row in linkRows" :key="row.key" class="rows__item">
                            <i class="bi rows__icon" :class="row.icon" aria-hidden="true"></i>
                            <div class="min-w-0">
                                <span class="field-label">{{ row.label }}</span>
                                <a v-if="row.href" :href="row.href" target="_blank" rel="noopener noreferrer" class="rows__value rows__link">{{ row.text }}</a>
                                <span v-else class="rows__value">—</span>
                            </div>
                        </li>
                    </ul>
                </section>

                <section class="panel">
                    <header class="panel__head"><h2 class="panel__title">Tags</h2></header>
                    <div class="panel__body">
                        <div v-if="product.tags?.length" class="d-flex flex-wrap gap-1">
                            <span v-for="tag in product.tags" :key="tag" class="badge text-bg-light border">{{ tag }}</span>
                        </div>
                        <p v-else class="empty">No tags.</p>
                    </div>
                </section>

                <section class="panel">
                    <header class="panel__head"><h2 class="panel__title">Activity</h2></header>
                    <dl class="activity">
                        <div v-for="row in activityRows" :key="row.key" class="activity__item">
                            <dt>{{ row.label }}</dt>
                            <dd>{{ row.value }}</dd>
                        </div>
                    </dl>
                </section>
            </aside>
        </div>

        <DeleteConfirmModal
            :show="confirmingDelete"
            title="Delete product"
            :message="`Delete ${product.name}?`"
            :loading="busy"
            @confirm="remove"
            @cancel="confirmingDelete = false"
        />
    </div>
</template>

<style scoped>
/* Same structure as the contact, company and deal detail pages; every value comes from the global --crm-* tokens. */
.product-view {
    --dv-gap: 1.25rem;
    --dv-radius: 0.75rem;

    display: flex;
    flex-direction: column;
    gap: var(--dv-gap);
    font-family: var(--crm-font);
    font-size: var(--crm-fs);
    line-height: 1.5;
    color: var(--crm-text);
    -webkit-font-smoothing: antialiased;
}

.min-w-0 {
    min-width: 0;
}

/* Breadcrumb */
.product-view__crumbs {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: var(--crm-fs-sm);
    color: var(--crm-text-muted);
}

.product-view__crumbs a {
    color: inherit;
    text-decoration: none;
}

.product-view__crumbs a:hover {
    color: var(--crm-primary);
}

.product-view__crumbs i {
    font-size: 0.625rem;
}

.product-view__crumbs [aria-current] {
    font-weight: 500;
    color: var(--crm-text-strong);
}

/* Header */
.product-view__header {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
}

.product-view__identity {
    display: flex;
    align-items: center;
    gap: 1rem;
    min-width: 0;
}

.avatar {
    display: grid;
    flex: none;
    place-items: center;
    width: 3.5rem;
    height: 3.5rem;
    font-size: 1.375rem;
    color: var(--crm-primary);
    background: var(--crm-primary-soft);
    overflow: hidden;
    border-radius: 0.75rem;
}

.avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.product-view__name {
    margin: 0;
    font-size: 1.5rem;
    font-weight: 600;
    letter-spacing: -0.02em;
    line-height: 1.25;
}

.product-view .badge {
    font-size: var(--crm-fs-xs);
    font-weight: 500;
}

.product-view__subtitle {
    margin: 0.125rem 0 0;
    color: var(--crm-text-muted);
}

.product-view__actions {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.product-view__actions .btn {
    --bs-btn-font-size: var(--crm-fs);
    --bs-btn-font-weight: 500;
    --bs-btn-border-radius: var(--crm-radius);
    display: inline-flex;
    align-items: center;
    min-height: var(--crm-control-h);
}

/* Panel: the one container pattern used across the page */
.panel {
    background: var(--crm-surface);
    border: 1px solid var(--crm-border);
    border-radius: var(--dv-radius);
}

.panel__head {
    padding: 0.875rem 1.25rem;
    border-bottom: 1px solid var(--crm-border);
}

.panel__title {
    margin: 0;
    font-size: 0.9375rem;
    font-weight: 600;
}

.panel__body {
    padding: 1.25rem;
}

.field-label {
    display: block;
    font-size: var(--crm-fs-sm);
    font-weight: 500;
    color: var(--crm-text-muted);
}

/* Key facts strip */
.facts {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(10rem, 1fr));
}

.facts__cell {
    display: flex;
    flex-direction: column;
    gap: 0.375rem;
    padding: 1rem 1.25rem;
}

.facts__cell + .facts__cell {
    border-left: 1px solid var(--crm-border);
}

.facts__value {
    font-size: 1.125rem;
    font-weight: 600;
    font-variant-numeric: tabular-nums;
    line-height: 1.3;
}

/* Layout */
.product-view__grid {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 20rem;
    gap: var(--dv-gap);
    align-items: start;
}

.product-view__main,
.product-view__side {
    display: flex;
    flex-direction: column;
    gap: var(--dv-gap);
    min-width: 0;
}

/* Product information grid */
.info {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(12rem, 1fr));
    gap: 1.25rem 1.5rem;
    padding: 1.25rem;
    margin: 0;
}

.info__value {
    margin: 0.125rem 0 0;
    font-weight: 500;
    overflow-wrap: anywhere;
}

/* Description */
.notes {
    max-width: 70ch;
    margin: 0;
    color: var(--crm-text-strong);
    white-space: pre-wrap;
    overflow-wrap: anywhere;
}

.notes--lead {
    margin-bottom: 0.75rem;
    font-weight: 500;
}

.empty {
    margin: 0;
    color: var(--crm-text-muted);
}

/* Relationship rows */
.rows {
    padding: 0.5rem 0;
    margin: 0;
    list-style: none;
}

.rows__item {
    display: flex;
    gap: 0.75rem;
    padding: 0.625rem 1.25rem;
}

.rows__icon {
    flex: none;
    margin-top: 0.125rem;
    color: var(--crm-text-muted);
}

.rows__value {
    display: block;
    overflow-wrap: anywhere;
}

.rows__link {
    color: var(--crm-text);
    text-decoration: none;
}

.rows__link:hover {
    color: var(--crm-primary);
    text-decoration: underline;
}

/* Activity */
.activity {
    padding: 0.5rem 0;
    margin: 0;
}

.activity__item {
    display: flex;
    justify-content: space-between;
    gap: 1rem;
    padding: 0.5rem 1.25rem;
}

.activity__item dt {
    font-weight: 400;
    color: var(--crm-text-muted);
}

.activity__item dd {
    margin: 0;
    font-weight: 500;
    text-align: right;
    font-variant-numeric: tabular-nums;
}

/* Responsive */
@media (max-width: 991.98px) {
    .product-view__grid {
        grid-template-columns: minmax(0, 1fr);
    }
}

@media (max-width: 575.98px) {
    .facts__cell + .facts__cell {
        border-left: 0;
        border-top: 1px solid var(--crm-border);
    }

    .product-view__actions {
        width: 100%;
    }
}
</style>
