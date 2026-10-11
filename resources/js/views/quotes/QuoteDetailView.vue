<script setup>
import { computed, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import { quotesApi } from '@/api/quotes';
import { useToastStore } from '@/stores/toast';
import { formatDateOnly } from '@/utils/dateOnly';
import { parseApiError } from '@/utils/errors';
import { formatDate } from '@/utils/format';
import { formatMoney } from '@/utils/money';
import { STATUS_ACTIONS, formatQuantity, statusBadge, statusMeta } from '@/utils/quoteOptions';
import DeleteConfirmModal from '@/components/DeleteConfirmModal.vue';
import LoadingBlock from '@/components/LoadingBlock.vue';

const props = defineProps({ id: { type: String, required: true } });

const router = useRouter();
const toast = useToastStore();

/* -------------------------------------------------------------------------- */
/* State                                                                      */
/* -------------------------------------------------------------------------- */

const quote = ref(null);
const busy = ref(false);
const confirmingDelete = ref(false);

watch(
    () => props.id,
    async (id) => {
        quote.value = null;
        try {
            quote.value = await quotesApi.get(id);
        } catch {
            toast.error('Quote not found.');
            router.replace({ name: 'quotes.index' });
        }
    },
    { immediate: true },
);

/* -------------------------------------------------------------------------- */
/* Presentation (all derived from `quote`, so the template stays declarative) */
/* -------------------------------------------------------------------------- */

const dash = (value) => value || '—';
const date = (value) => (value ? formatDate(value, true) : '—');
const money = (amount) => formatMoney(amount, quote.value.currency);

const status = computed(() => statusMeta(quote.value.status));
const actions = computed(() => STATUS_ACTIONS[quote.value.status] ?? []);

const subtitle = computed(() => {
    const { company, contact } = quote.value;
    return [company?.name, contact?.name].filter(Boolean).join(' · ') || 'No company or contact';
});

/** Draft → Sent → Accepted / Rejected. A decided quote shows its outcome as the last step. */
const track = computed(() => {
    const current = quote.value.status;
    const decided = current === 'accepted' || current === 'rejected';
    const sentDone = current !== 'draft';

    const steps = [
        { id: 'draft', name: 'Draft', state: sentDone ? 'done' : 'current' },
        { id: 'sent', name: 'Sent', state: decided ? 'done' : current === 'sent' ? 'current' : 'idle' },
    ];

    if (current === 'rejected') steps.push({ id: 'rejected', name: 'Rejected', state: 'lost' });
    else steps.push({ id: 'accepted', name: 'Accepted', state: current === 'accepted' ? 'won' : 'idle' });

    return steps;
});

// Add a row here and it renders; no template changes needed.
const factCells = computed(() => {
    const q = quote.value;
    return [
        { key: 'total', label: 'Total', value: money(q.total) },
        { key: 'tax', label: 'Tax', value: money(q.tax_total) },
        { key: 'issue', label: 'Issue date', value: formatDateOnly(q.issue_date) },
        { key: 'valid', label: 'Valid until', value: formatDateOnly(q.valid_until), danger: q.is_expired },
    ];
});

const detailRows = computed(() => {
    const q = quote.value;
    return [
        { key: 'number', label: 'Quote number', value: q.quote_number },
        { key: 'owner', label: 'Quote owner', value: q.owner?.name ?? 'Unassigned' },
        { key: 'status', label: 'Status', value: dash(status.value?.label) },
        { key: 'currency', label: 'Currency', value: dash(q.currency) },
        { key: 'issue', label: 'Issue date', value: formatDateOnly(q.issue_date) },
        { key: 'valid', label: 'Valid until', value: formatDateOnly(q.valid_until) },
    ];
});

const totalRows = computed(() => {
    const q = quote.value;
    return [
        { key: 'subtotal', label: 'Subtotal', value: money(q.subtotal) },
        { key: 'discount', label: 'Discount', value: `−${money(q.discount_total)}` },
        { key: 'tax', label: 'Tax', value: money(q.tax_total) },
    ];
});

const relationRows = computed(() => {
    const q = quote.value;
    return [
        { key: 'company', icon: 'bi-building', label: 'Company', text: q.company?.name, to: q.company ? { name: 'companies.show', params: { id: q.company.id } } : null },
        { key: 'contact', icon: 'bi-person', label: 'Contact', text: q.contact?.name, to: q.contact ? { name: 'contacts.show', params: { id: q.contact.id } } : null },
        { key: 'deal', icon: 'bi-briefcase', label: 'Deal', text: q.deal?.name, to: q.deal ? { name: 'deals.show', params: { id: q.deal.id } } : null },
    ];
});

const activityRows = computed(() => {
    const q = quote.value;
    const rows = [
        { key: 'created', label: 'Created', value: date(q.created_at) },
        { key: 'updated', label: 'Last updated', value: date(q.updated_at) },
    ];
    if (q.sent_at) rows.push({ key: 'sent', label: 'Sent', value: date(q.sent_at) });
    if (q.accepted_at) rows.push({ key: 'accepted', label: 'Accepted', value: date(q.accepted_at) });
    if (q.rejected_at) rows.push({ key: 'rejected', label: 'Rejected', value: date(q.rejected_at) });
    return rows;
});

/* -------------------------------------------------------------------------- */
/* Actions                                                                    */
/* -------------------------------------------------------------------------- */

async function changeStatus(to) {
    busy.value = true;
    try {
        // The endpoint returns the full quote; merging keeps the page intact if a field is ever omitted.
        const updated = await quotesApi.setStatus(props.id, to);
        quote.value = { ...quote.value, ...updated, items: updated.items ?? quote.value.items };
        toast.success(`Quote marked as ${statusMeta(to)?.label.toLowerCase() ?? to}.`);
    } catch (e) {
        toast.error(parseApiError(e).message);
    } finally {
        busy.value = false;
    }
}

async function remove() {
    busy.value = true;
    try {
        await quotesApi.remove(props.id);
        toast.success('Quote deleted.');
        router.push({ name: 'quotes.index' });
    } catch (e) {
        toast.error(parseApiError(e).message);
        confirmingDelete.value = false;
    } finally {
        busy.value = false;
    }
}

const printPage = () => window.print();
</script>

<template>
    <LoadingBlock v-if="!quote" />

    <div v-else class="quote-view">
        <!-- Breadcrumb -->
        <nav class="quote-view__crumbs no-print" aria-label="Breadcrumb">
            <RouterLink :to="{ name: 'quotes.index' }">Quotes</RouterLink>
            <i class="bi bi-chevron-right" aria-hidden="true"></i>
            <span aria-current="page">{{ quote.quote_number }}</span>
        </nav>

        <!-- Header -->
        <header class="quote-view__header">
            <div class="quote-view__identity">
                <div class="avatar" aria-hidden="true"><i class="bi bi-file-earmark-text"></i></div>
                <div class="min-w-0">
                    <div class="d-flex align-items-center flex-wrap gap-2">
                        <h1 class="quote-view__name">{{ quote.subject }}</h1>
                        <span class="badge" :class="statusBadge(quote.status)">{{ status?.label ?? quote.status }}</span>
                        <span v-if="quote.is_expired" class="badge text-bg-danger">Expired</span>
                    </div>
                    <p class="quote-view__subtitle">{{ quote.quote_number }} · {{ subtitle }}</p>
                </div>
            </div>

            <div class="quote-view__actions no-print">
                <button
                    v-for="a in actions"
                    :key="a.to"
                    type="button"
                    class="btn"
                    :class="a.primary ? 'btn-primary' : 'btn-outline-secondary'"
                    :disabled="busy"
                    @click="changeStatus(a.to)"
                >
                    <i class="bi me-2" :class="a.icon"></i>{{ a.label }}
                </button>
                <button type="button" class="btn btn-outline-secondary" @click="printPage">
                    <i class="bi bi-printer me-2"></i>Print
                </button>
                <RouterLink :to="{ name: 'quotes.edit', params: { id } }" class="btn btn-outline-secondary">
                    <i class="bi bi-pencil me-2"></i>Edit
                </RouterLink>
                <button type="button" class="btn btn-outline-danger" :disabled="busy" @click="confirmingDelete = true">
                    <i class="bi bi-trash me-2"></i>Delete
                </button>
            </div>
        </header>

        <!-- Status track -->
        <ol class="panel track no-print" aria-label="Quote status">
            <li v-for="s in track" :key="s.id" class="track__step" :class="`is-${s.state}`" :aria-current="s.state === 'current' ? 'step' : null">
                <span class="track__dot" aria-hidden="true">
                    <i v-if="s.state === 'done' || s.state === 'won'" class="bi bi-check-lg"></i>
                    <i v-else-if="s.state === 'lost'" class="bi bi-x-lg"></i>
                </span>
                <span class="track__name">{{ s.name }}</span>
            </li>
        </ol>

        <!-- Key facts -->
        <section class="panel facts" aria-label="Key facts">
            <div v-for="cell in factCells" :key="cell.key" class="facts__cell">
                <span class="field-label">{{ cell.label }}</span>
                <strong class="facts__value" :class="{ 'text-danger': cell.danger }">{{ cell.value }}</strong>
            </div>
        </section>

        <!-- Body -->
        <div class="quote-view__grid">
            <main class="quote-view__main">
                <section class="panel">
                    <header class="panel__head"><h2 class="panel__title">Line items</h2></header>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0 items-table">
                            <thead>
                                <tr>
                                    <th scope="col">Item</th>
                                    <th scope="col" class="text-end">Qty</th>
                                    <th scope="col" class="text-end">Unit price</th>
                                    <th scope="col" class="text-end">Disc.</th>
                                    <th scope="col" class="text-end">Tax</th>
                                    <th scope="col" class="text-end">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="!quote.items?.length">
                                    <td colspan="6" class="text-center text-muted py-4">No line items.</td>
                                </tr>
                                <tr v-for="item in quote.items" :key="item.id">
                                    <td class="items-table__name">
                                        <strong>{{ item.name }}</strong>
                                        <small v-if="item.description">{{ item.description }}</small>
                                    </td>
                                    <td class="text-end">{{ formatQuantity(item.quantity) }}<span v-if="item.unit" class="text-muted"> {{ item.unit }}</span></td>
                                    <td class="text-end">{{ money(item.unit_price) }}</td>
                                    <td class="text-end">{{ Number(item.discount_percent) ? `${Number(item.discount_percent)}%` : '—' }}</td>
                                    <td class="text-end">{{ Number(item.tax_rate) ? `${Number(item.tax_rate)}%` : '—' }}</td>
                                    <td class="text-end">{{ money(item.line_total) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <dl class="totals">
                        <div v-for="row in totalRows" :key="row.key" class="totals__item">
                            <dt>{{ row.label }}</dt>
                            <dd>{{ row.value }}</dd>
                        </div>
                        <div class="totals__item totals__grand">
                            <dt>Total</dt>
                            <dd>{{ money(quote.total) }}</dd>
                        </div>
                    </dl>
                </section>

                <section class="panel">
                    <header class="panel__head"><h2 class="panel__title">Quote information</h2></header>
                    <dl class="info">
                        <div v-for="row in detailRows" :key="row.key" class="info__item">
                            <dt class="field-label">{{ row.label }}</dt>
                            <dd class="info__value">{{ row.value }}</dd>
                        </div>
                    </dl>
                </section>

                <section class="panel">
                    <header class="panel__head"><h2 class="panel__title">Terms &amp; conditions</h2></header>
                    <div class="panel__body">
                        <p v-if="quote.terms" class="notes">{{ quote.terms }}</p>
                        <p v-else class="empty">No terms yet. Add them by editing this quote.</p>
                    </div>
                </section>

                <section v-if="quote.notes" class="panel no-print">
                    <header class="panel__head"><h2 class="panel__title">Internal notes</h2></header>
                    <div class="panel__body">
                        <p class="notes">{{ quote.notes }}</p>
                    </div>
                </section>
            </main>

            <aside class="quote-view__side">
                <section class="panel">
                    <header class="panel__head"><h2 class="panel__title">Relationships</h2></header>
                    <ul class="rows">
                        <li v-for="row in relationRows" :key="row.key" class="rows__item">
                            <i class="bi rows__icon" :class="row.icon" aria-hidden="true"></i>
                            <div class="min-w-0">
                                <span class="field-label">{{ row.label }}</span>
                                <RouterLink v-if="row.to" :to="row.to" class="rows__value rows__link">{{ row.text }}</RouterLink>
                                <span v-else class="rows__value">—</span>
                            </div>
                        </li>
                    </ul>
                </section>

                <section class="panel no-print">
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
            title="Delete quote"
            :message="`Delete ${quote.quote_number}?`"
            :loading="busy"
            @confirm="remove"
            @cancel="confirmingDelete = false"
        />
    </div>
</template>

<style scoped>
/* Same structure as the contact, company, deal and product detail pages; every value comes from the global --crm-* tokens. */
.quote-view {
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
.quote-view__crumbs {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: var(--crm-fs-sm);
    color: var(--crm-text-muted);
}

.quote-view__crumbs a {
    color: inherit;
    text-decoration: none;
}

.quote-view__crumbs a:hover {
    color: var(--crm-primary);
}

.quote-view__crumbs i {
    font-size: 0.625rem;
}

.quote-view__crumbs [aria-current] {
    font-weight: 500;
    color: var(--crm-text-strong);
}

/* Header */
.quote-view__header {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
}

.quote-view__identity {
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
    border-radius: 0.75rem;
}

.quote-view__name {
    margin: 0;
    font-size: 1.5rem;
    font-weight: 600;
    letter-spacing: -0.02em;
    line-height: 1.25;
}

.quote-view .badge {
    font-size: var(--crm-fs-xs);
    font-weight: 500;
}

.quote-view__subtitle {
    margin: 0.125rem 0 0;
    color: var(--crm-text-muted);
}

.quote-view__actions {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.quote-view__actions .btn {
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

/* Pipeline track */
.track {
    display: flex;
    gap: 0.5rem;
    padding: 1rem 1.25rem;
    margin: 0;
    overflow-x: auto;
    list-style: none;
}

.track__step {
    position: relative;
    display: flex;
    flex: 1 1 0;
    flex-direction: column;
    gap: 0.5rem;
    align-items: flex-start;
    min-width: 6.5rem;
    font-size: var(--crm-fs-sm);
    font-weight: 500;
    color: var(--crm-text-muted);
}

/* Connector line between dots */
.track__step::before {
    position: absolute;
    top: 0.6875rem;
    left: 1.5rem;
    right: -0.5rem;
    height: 2px;
    content: '';
    background: var(--crm-border);
}

.track__step:last-child::before {
    display: none;
}

.track__step.is-done::before {
    background: var(--crm-primary);
}

.track__dot {
    position: relative;
    display: grid;
    place-items: center;
    width: 1.5rem;
    height: 1.5rem;
    font-size: 0.75rem;
    color: #fff;
    background: var(--crm-surface);
    border: 2px solid var(--crm-border);
    border-radius: 50%;
}

.track__step.is-done .track__dot,
.track__step.is-current .track__dot {
    background: var(--crm-primary);
    border-color: var(--crm-primary);
}

.track__step.is-current {
    color: var(--crm-primary);
}

.track__step.is-done {
    color: var(--crm-text-strong);
}

.track__step.is-won {
    color: var(--bs-success);
}

.track__step.is-won .track__dot {
    background: var(--bs-success);
    border-color: var(--bs-success);
}

.track__step.is-lost {
    color: var(--bs-danger);
}

.track__step.is-lost .track__dot {
    background: var(--bs-danger);
    border-color: var(--bs-danger);
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
.quote-view__grid {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 20rem;
    gap: var(--dv-gap);
    align-items: start;
}

.quote-view__main,
.quote-view__side {
    display: flex;
    flex-direction: column;
    gap: var(--dv-gap);
    min-width: 0;
}

/* Quote information grid */
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

/* Line items */
.items-table {
    --bs-table-bg: transparent;
    font-size: var(--crm-fs);
    color: var(--crm-text);
    font-variant-numeric: tabular-nums;
}

.items-table thead th {
    padding: 0.75rem 1.25rem;
    font-size: var(--crm-fs-sm);
    font-weight: 600;
    color: var(--crm-text-strong);
    white-space: nowrap;
    border-bottom: 0;
    box-shadow: inset 0 -1px 0 var(--crm-border);
}

.items-table tbody td {
    padding: 0.75rem 1.25rem;
    border-bottom: 1px solid var(--crm-border);
}

.items-table tbody tr:last-child td {
    border-bottom: 0;
}

.items-table__name {
    min-width: 12rem;
}

.items-table__name strong,
.items-table__name small {
    display: block;
}

.items-table__name small {
    color: var(--crm-text-muted);
}

/* Totals under the table */
.totals {
    display: flex;
    flex-direction: column;
    gap: 0.375rem;
    max-width: 22rem;
    padding: 1rem 1.25rem;
    margin: 0 0 0 auto;
    font-variant-numeric: tabular-nums;
    border-top: 1px solid var(--crm-border);
}

.totals__item {
    display: flex;
    justify-content: space-between;
    gap: 1rem;
}

.totals dt {
    font-weight: 400;
    color: var(--crm-text-muted);
}

.totals dd {
    margin: 0;
    font-weight: 500;
}

.totals__grand {
    padding-top: 0.5rem;
    margin-top: 0.25rem;
    border-top: 1px solid var(--crm-border);
}

.totals__grand dt,
.totals__grand dd {
    font-size: 1.125rem;
    font-weight: 600;
    color: var(--crm-text-strong);
}

/* Responsive */
@media (max-width: 991.98px) {
    .quote-view__grid {
        grid-template-columns: minmax(0, 1fr);
    }
}

@media print {
    .no-print {
        display: none !important;
    }

    .quote-view__grid {
        grid-template-columns: minmax(0, 1fr);
    }
}

@media (max-width: 575.98px) {
    .facts__cell + .facts__cell {
        border-left: 0;
        border-top: 1px solid var(--crm-border);
    }

    .quote-view__actions {
        width: 100%;
    }
}
</style>
