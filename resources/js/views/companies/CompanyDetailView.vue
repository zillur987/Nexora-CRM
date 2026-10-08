<script setup>
import { computed, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import { companiesApi } from '@/api/companies';
import { companySizeMeta } from '@/constants';
import { useToastStore } from '@/stores/toast';
import { parseApiError } from '@/utils/errors';
import { displayUrl, formatDate, money } from '@/utils/format';
import DeleteConfirmModal from '@/components/DeleteConfirmModal.vue';
import LoadingBlock from '@/components/LoadingBlock.vue';

const props = defineProps({ id: { type: String, required: true } });

const router = useRouter();
const toast = useToastStore();

/* -------------------------------------------------------------------------- */
/* State                                                                      */
/* -------------------------------------------------------------------------- */

const company = ref(null);
const busy = ref(false);
const confirmingDelete = ref(false);

watch(
    () => props.id,
    async (id) => {
        company.value = null;
        try {
            company.value = await companiesApi.get(id);
        } catch {
            toast.error('Company not found.');
            router.replace({ name: 'companies.index' });
        }
    },
    { immediate: true },
);

/* -------------------------------------------------------------------------- */
/* Presentation (all derived from `company`, so the template stays declarative) */
/* -------------------------------------------------------------------------- */

const dash = (value) => value || '—';
const date = (value) => (value ? formatDate(value, true) : '—');

const initials = computed(() =>
    (company.value?.name ?? '')
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0].toUpperCase())
        .join(''),
);

const subtitle = computed(() => {
    const { industry, size } = company.value;
    return [industry?.name, companySizeMeta[size]?.label].filter(Boolean).join(' · ') || 'No company details';
});

const revenueLabel = computed(() =>
    company.value.annual_revenue ? money(company.value.annual_revenue, company.value.currency) : '—',
);

// Add a row here and it renders; no template changes needed.
const contactRows = computed(() => {
    const c = company.value;
    return [
        { key: 'phone', icon: 'bi-telephone', label: 'Phone', value: c.phone, href: c.phone ? `tel:${c.phone}` : null },
        { key: 'email', icon: 'bi-envelope', label: 'Email', value: c.email, href: c.email ? `mailto:${c.email}` : null },
        { key: 'website', icon: 'bi-globe2', label: 'Website', value: displayUrl(c.website), href: c.website, external: true },
        { key: 'linkedin', icon: 'bi-linkedin', label: 'LinkedIn', value: displayUrl(c.linkedin), href: c.linkedin, external: true },
        { key: 'twitter', icon: 'bi-twitter-x', label: 'Twitter / X', value: displayUrl(c.twitter), href: c.twitter, external: true },
        { key: 'instagram', icon: 'bi-instagram', label: 'Instagram', value: displayUrl(c.instagram), href: c.instagram, external: true },
        { key: 'facebook', icon: 'bi-facebook', label: 'Facebook', value: displayUrl(c.facebook), href: c.facebook, external: true },
    ];
});

const detailRows = computed(() => {
    const c = company.value;
    return [
        { key: 'name', label: 'Company name', value: dash(c.name) },
        { key: 'owner', label: 'Company owner', value: c.owner?.name ?? 'Unassigned' },
        { key: 'industry', label: 'Industry', value: dash(c.industry?.name) },
        { key: 'type', label: 'Company type', value: dash(c.type?.name) },
        { key: 'size', label: 'Company size', value: dash(companySizeMeta[c.size]?.label) },
        { key: 'revenue', label: 'Annual revenue', value: revenueLabel.value },
        { key: 'currency', label: 'Currency', value: dash(c.currency) },
    ];
});

const addresses = computed(() => [
    { key: 'billing', icon: 'bi-receipt', title: 'Billing address', value: company.value.billing_address },
    { key: 'shipping', icon: 'bi-truck', title: 'Shipping address', value: company.value.shipping_address },
]);

const activityRows = computed(() => [
    { key: 'created', label: 'Created', value: date(company.value.created_at) },
    { key: 'updated', label: 'Last updated', value: date(company.value.updated_at) },
]);

/* -------------------------------------------------------------------------- */
/* Actions                                                                    */
/* -------------------------------------------------------------------------- */

async function remove() {
    busy.value = true;
    try {
        await companiesApi.remove(props.id);
        toast.success('Company deleted.');
        router.push({ name: 'companies.index' });
    } catch (e) {
        toast.error(parseApiError(e).message); // e.g. "This company has 2 subsidiaries…"
        confirmingDelete.value = false;
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <LoadingBlock v-if="!company" />

    <div v-else class="company-view">
        <!-- Breadcrumb -->
        <nav class="company-view__crumbs" aria-label="Breadcrumb">
            <RouterLink :to="{ name: 'companies.index' }">Companies</RouterLink>
            <i class="bi bi-chevron-right" aria-hidden="true"></i>
            <span aria-current="page">{{ company.name }}</span>
        </nav>

        <!-- Header -->
        <header class="company-view__header">
            <div class="company-view__identity">
                <div class="avatar" aria-hidden="true">{{ initials }}</div>
                <div class="min-w-0">
                    <div class="d-flex align-items-center flex-wrap gap-2">
                        <h1 class="company-view__name">{{ company.name }}</h1>
                        <span v-if="company.type" class="badge text-bg-primary">{{ company.type.name }}</span>
                    </div>
                    <p class="company-view__subtitle">{{ subtitle }}</p>
                </div>
            </div>

            <div class="company-view__actions">
                <RouterLink :to="{ name: 'companies.edit', params: { id } }" class="btn btn-outline-secondary">
                    <i class="bi bi-pencil me-2"></i>Edit
                </RouterLink>
                <button type="button" class="btn btn-outline-danger" :disabled="busy" @click="confirmingDelete = true">
                    <i class="bi bi-trash me-2"></i>Delete
                </button>
            </div>
        </header>

        <!-- Key facts -->
        <section class="panel facts" aria-label="Key facts">
            <div class="facts__cell">
                <span class="field-label">Annual revenue</span>
                <strong class="facts__value">{{ revenueLabel }}</strong>
            </div>
            <div class="facts__cell">
                <span class="field-label">Industry</span>
                <strong class="facts__value">{{ dash(company.industry?.name) }}</strong>
            </div>
            <div class="facts__cell">
                <span class="field-label">Company size</span>
                <strong class="facts__value">{{ dash(companySizeMeta[company.size]?.label) }}</strong>
            </div>
            <div class="facts__cell">
                <span class="field-label">Owner</span>
                <strong class="facts__value">{{ company.owner?.name ?? 'Unassigned' }}</strong>
            </div>
        </section>

        <!-- Body -->
        <div class="company-view__grid">
            <main class="company-view__main">
                <section class="panel">
                    <header class="panel__head"><h2 class="panel__title">Company information</h2></header>
                    <dl class="info">
                        <div v-for="row in detailRows" :key="row.key" class="info__item">
                            <dt class="field-label">{{ row.label }}</dt>
                            <dd class="info__value">{{ row.value }}</dd>
                        </div>

                        <div class="info__item">
                            <dt class="field-label">Parent company</dt>
                            <dd class="info__value">
                                <RouterLink v-if="company.parent" :to="{ name: 'companies.show', params: { id: company.parent.id } }" class="rows__link">
                                    {{ company.parent.name }}
                                </RouterLink>
                                <template v-else>—</template>
                            </dd>
                        </div>
                    </dl>
                </section>

                <section class="panel">
                    <header class="panel__head"><h2 class="panel__title">Addresses</h2></header>
                    <div class="addresses">
                        <div v-for="a in addresses" :key="a.key" class="addresses__item">
                            <span class="field-label"><i class="bi me-2" :class="a.icon" aria-hidden="true"></i>{{ a.title }}</span>
                            <p class="addresses__value">{{ dash(a.value) }}</p>
                        </div>
                    </div>
                </section>

                <section class="panel">
                    <header class="panel__head"><h2 class="panel__title">Description</h2></header>
                    <div class="panel__body">
                        <p v-if="company.description" class="notes">{{ company.description }}</p>
                        <p v-else class="empty">No description yet. Add context by editing this company.</p>
                    </div>
                </section>

                <section v-if="company.children?.length" class="panel">
                    <header class="panel__head">
                        <h2 class="panel__title">Subsidiaries <span class="text-muted fw-normal">({{ company.children_count }})</span></h2>
                    </header>
                    <ul class="rows">
                        <li v-for="child in company.children" :key="child.id" class="rows__item">
                            <i class="bi bi-diagram-3 rows__icon" aria-hidden="true"></i>
                            <RouterLink :to="{ name: 'companies.show', params: { id: child.id } }" class="rows__value rows__link">
                                {{ child.name }}
                            </RouterLink>
                        </li>
                    </ul>
                </section>
            </main>

            <aside class="company-view__side">
                <section class="panel">
                    <header class="panel__head"><h2 class="panel__title">Contact details</h2></header>
                    <ul class="rows">
                        <li v-for="row in contactRows" :key="row.key" class="rows__item">
                            <i class="bi rows__icon" :class="row.icon" aria-hidden="true"></i>
                            <div class="min-w-0">
                                <span class="field-label">{{ row.label }}</span>
                                <a
                                    v-if="row.href && row.value"
                                    :href="row.href"
                                    class="rows__value rows__link"
                                    :target="row.external ? '_blank' : null"
                                    :rel="row.external ? 'noopener noreferrer' : null"
                                >
                                    {{ row.value }}
                                </a>
                                <span v-else class="rows__value">{{ dash(row.value) }}</span>
                            </div>
                        </li>
                    </ul>
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
            title="Delete company"
            :message="`Delete ${company.name}?`"
            :loading="busy"
            @confirm="remove"
            @cancel="confirmingDelete = false"
        />
    </div>
</template>

<style scoped>
/* Same structure as the lead detail page; every value comes from the global --crm-* tokens. */
.company-view {
    --cv-gap: 1.25rem;
    --cv-radius: 0.75rem;

    display: flex;
    flex-direction: column;
    gap: var(--cv-gap);
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
.company-view__crumbs {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: var(--crm-fs-sm);
    color: var(--crm-text-muted);
}

.company-view__crumbs a {
    color: inherit;
    text-decoration: none;
}

.company-view__crumbs a:hover {
    color: var(--crm-primary);
}

.company-view__crumbs i {
    font-size: 0.625rem;
}

.company-view__crumbs [aria-current] {
    font-weight: 500;
    color: var(--crm-text-strong);
}

/* Header */
.company-view__header {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
}

.company-view__identity {
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
    font-size: 1.125rem;
    font-weight: 600;
    color: var(--crm-primary);
    background: var(--crm-primary-soft);
    border-radius: 0.75rem;
}

.company-view__name {
    margin: 0;
    font-size: 1.5rem;
    font-weight: 600;
    letter-spacing: -0.02em;
    line-height: 1.25;
}

.company-view__subtitle {
    margin: 0.125rem 0 0;
    color: var(--crm-text-muted);
}

.company-view__actions {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.company-view__actions .btn {
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
    border-radius: var(--cv-radius);
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
.company-view__grid {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 20rem;
    gap: var(--cv-gap);
    align-items: start;
}

.company-view__main,
.company-view__side {
    display: flex;
    flex-direction: column;
    gap: var(--cv-gap);
    min-width: 0;
}

/* Company information grid */
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

/* Addresses */
.addresses {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(14rem, 1fr));
    gap: 1.25rem 1.5rem;
    padding: 1.25rem;
}

.addresses__value {
    margin: 0.25rem 0 0;
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

.empty {
    margin: 0;
    color: var(--crm-text-muted);
}

/* Contact rows */
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
    .company-view__grid {
        grid-template-columns: minmax(0, 1fr);
    }
}

@media (max-width: 575.98px) {
    .facts__cell + .facts__cell {
        border-left: 0;
        border-top: 1px solid var(--crm-border);
    }

    .company-view__actions {
        width: 100%;
    }
}
</style>
