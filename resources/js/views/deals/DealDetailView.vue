<script setup>
import { computed, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import { dealLookupsApi, dealsApi } from '@/api/deals';
import { useToastStore } from '@/stores/toast';
import { priorityMeta, stageBadge } from '@/utils/dealOptions';
import { formatDateOnly } from '@/utils/dateOnly';
import { parseApiError } from '@/utils/errors';
import { formatDate } from '@/utils/format';
import { formatMoney } from '@/utils/money';
import DeleteConfirmModal from '@/components/DeleteConfirmModal.vue';
import LoadingBlock from '@/components/LoadingBlock.vue';

const props = defineProps({ id: { type: String, required: true } });

const router = useRouter();
const toast = useToastStore();

/* -------------------------------------------------------------------------- */
/* State                                                                      */
/* -------------------------------------------------------------------------- */

const deal = ref(null);
const stages = ref([]);
const busy = ref(false);
const confirmingDelete = ref(false);

watch(
    () => props.id,
    async (id) => {
        deal.value = null;
        try {
            deal.value = await dealsApi.get(id);
        } catch {
            toast.error('Deal not found.');
            router.replace({ name: 'deals.index' });
        }
    },
    { immediate: true },
);

// The pipeline track is decoration: if the stages fail to load it simply isn't shown.
dealLookupsApi
    .list('deal-stages')
    .then((list) => (stages.value = list))
    .catch(() => {});

/* -------------------------------------------------------------------------- */
/* Presentation (all derived from `deal`, so the template stays declarative)  */
/* -------------------------------------------------------------------------- */

const dash = (value) => value || '—';
const date = (value) => (value ? formatDate(value, true) : '—');

const isClosed = computed(() => (deal.value.stage?.outcome ?? 'open') !== 'open');
const isWon = computed(() => deal.value.stage?.outcome === 'won');
const isLost = computed(() => deal.value.stage?.outcome === 'lost');
const priority = computed(() => priorityMeta(deal.value.priority));

const subtitle = computed(() => {
    const { company, contact } = deal.value;
    return [company?.name, contact?.name].filter(Boolean).join(' · ') || 'No company or contact';
});

/** Open stages in pipeline order; a closed deal adds its final (won / lost) stage at the end. */
const track = computed(() => {
    const current = deal.value.stage;
    const open = stages.value.filter((s) => s.outcome === 'open');
    const currentIndex = open.findIndex((s) => s.id === current?.id);

    const steps = open.map((s, index) => {
        let state = 'idle';
        if (isClosed.value) state = isWon.value ? 'done' : 'idle';
        else if (index < currentIndex) state = 'done';
        else if (index === currentIndex) state = 'current';

        return { id: s.id, name: s.name, state };
    });

    if (isClosed.value) steps.push({ id: current.id, name: current.name, state: isWon.value ? 'won' : 'lost' });

    return steps.length > 1 ? steps : [];
});

// Add a row here and it renders; no template changes needed.
const factCells = computed(() => {
    const d = deal.value;
    return [
        { key: 'amount', label: 'Amount', value: formatMoney(d.amount, d.currency) },
        { key: 'weighted', label: isClosed.value ? 'Final value' : 'Weighted value', value: formatMoney(isClosed.value ? (isWon.value ? d.amount : 0) : d.weighted_amount, d.currency) },
        { key: 'probability', label: 'Probability', value: d.probability === null ? '—' : `${d.probability}%` },
        { key: 'close', label: isClosed.value ? 'Closed on' : 'Expected close', value: formatDateOnly(isClosed.value ? d.actual_close_date : d.expected_close_date), danger: d.is_overdue },
    ];
});

const detailRows = computed(() => {
    const d = deal.value;
    const rows = [
        { key: 'owner', label: 'Deal owner', value: d.owner?.name ?? 'Unassigned' },
        { key: 'stage', label: 'Deal stage', value: dash(d.stage?.name) },
        { key: 'type', label: 'Deal type', value: dash(d.type?.name) },
        { key: 'source', label: 'Lead source', value: dash(d.lead_source?.name) },
        { key: 'expected', label: 'Expected close date', value: formatDateOnly(d.expected_close_date) },
        { key: 'next_step', label: 'Next step', value: dash(d.next_step) },
    ];

    if (isClosed.value) rows.push({ key: 'closed', label: 'Closed on', value: formatDateOnly(d.actual_close_date) });
    if (isLost.value) rows.push({ key: 'lost_reason', label: 'Lost reason', value: dash(d.lost_reason) });

    return rows;
});

const relationRows = computed(() => {
    const d = deal.value;
    return [
        { key: 'company', icon: 'bi-building', label: 'Company', text: d.company?.name, to: d.company ? { name: 'companies.show', params: { id: d.company.id } } : null },
        { key: 'contact', icon: 'bi-person', label: 'Contact', text: d.contact?.name, to: d.contact ? { name: 'contacts.show', params: { id: d.contact.id } } : null },
    ];
});

const activityRows = computed(() => [
    { key: 'created', label: 'Created', value: date(deal.value.created_at) },
    { key: 'updated', label: 'Last updated', value: date(deal.value.updated_at) },
]);

/* -------------------------------------------------------------------------- */
/* Actions                                                                    */
/* -------------------------------------------------------------------------- */

async function remove() {
    busy.value = true;
    try {
        await dealsApi.remove(props.id);
        toast.success('Deal deleted.');
        router.push({ name: 'deals.index' });
    } catch (e) {
        toast.error(parseApiError(e).message);
        confirmingDelete.value = false;
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <LoadingBlock v-if="!deal" />

    <div v-else class="deal-view">
        <!-- Breadcrumb -->
        <nav class="deal-view__crumbs" aria-label="Breadcrumb">
            <RouterLink :to="{ name: 'deals.index' }">Deals</RouterLink>
            <i class="bi bi-chevron-right" aria-hidden="true"></i>
            <span aria-current="page">{{ deal.name }}</span>
        </nav>

        <!-- Header -->
        <header class="deal-view__header">
            <div class="deal-view__identity">
                <div class="avatar" aria-hidden="true"><i class="bi bi-briefcase"></i></div>
                <div class="min-w-0">
                    <div class="d-flex align-items-center flex-wrap gap-2">
                        <h1 class="deal-view__name">{{ deal.name }}</h1>
                        <span v-if="deal.stage" class="badge" :class="stageBadge(deal.stage)">{{ deal.stage.name }}</span>
                        <span v-if="priority" class="badge" :class="priority.badge">{{ priority.label }} priority</span>
                        <span v-if="deal.is_overdue" class="badge text-bg-danger">Overdue</span>
                    </div>
                    <p class="deal-view__subtitle">{{ subtitle }}</p>
                </div>
            </div>

            <div class="deal-view__actions">
                <RouterLink :to="{ name: 'deals.edit', params: { id } }" class="btn btn-outline-secondary">
                    <i class="bi bi-pencil me-2"></i>Edit
                </RouterLink>
                <button type="button" class="btn btn-outline-danger" :disabled="busy" @click="confirmingDelete = true">
                    <i class="bi bi-trash me-2"></i>Delete
                </button>
            </div>
        </header>

        <!-- Pipeline track -->
        <ol v-if="track.length" class="panel track" aria-label="Pipeline stage">
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
        <div class="deal-view__grid">
            <main class="deal-view__main">
                <section class="panel">
                    <header class="panel__head"><h2 class="panel__title">Deal information</h2></header>
                    <dl class="info">
                        <div v-for="row in detailRows" :key="row.key" class="info__item">
                            <dt class="field-label">{{ row.label }}</dt>
                            <dd class="info__value">{{ row.value }}</dd>
                        </div>
                    </dl>
                </section>

                <section class="panel">
                    <header class="panel__head"><h2 class="panel__title">Description</h2></header>
                    <div class="panel__body">
                        <p v-if="deal.description" class="notes">{{ deal.description }}</p>
                        <p v-else class="empty">No description yet. Add context by editing this deal.</p>
                    </div>
                </section>
            </main>

            <aside class="deal-view__side">
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
            title="Delete deal"
            :message="`Delete ${deal.name}?`"
            :loading="busy"
            @confirm="remove"
            @cancel="confirmingDelete = false"
        />
    </div>
</template>

<style scoped>
/* Same structure as the contact and company detail pages; every value comes from the global --crm-* tokens. */
.deal-view {
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
.deal-view__crumbs {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: var(--crm-fs-sm);
    color: var(--crm-text-muted);
}

.deal-view__crumbs a {
    color: inherit;
    text-decoration: none;
}

.deal-view__crumbs a:hover {
    color: var(--crm-primary);
}

.deal-view__crumbs i {
    font-size: 0.625rem;
}

.deal-view__crumbs [aria-current] {
    font-weight: 500;
    color: var(--crm-text-strong);
}

/* Header */
.deal-view__header {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
}

.deal-view__identity {
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

.deal-view__name {
    margin: 0;
    font-size: 1.5rem;
    font-weight: 600;
    letter-spacing: -0.02em;
    line-height: 1.25;
}

.deal-view .badge {
    font-size: var(--crm-fs-xs);
    font-weight: 500;
}

.deal-view__subtitle {
    margin: 0.125rem 0 0;
    color: var(--crm-text-muted);
}

.deal-view__actions {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.deal-view__actions .btn {
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
.deal-view__grid {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 20rem;
    gap: var(--dv-gap);
    align-items: start;
}

.deal-view__main,
.deal-view__side {
    display: flex;
    flex-direction: column;
    gap: var(--dv-gap);
    min-width: 0;
}

/* Deal information grid */
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

/* Responsive */
@media (max-width: 991.98px) {
    .deal-view__grid {
        grid-template-columns: minmax(0, 1fr);
    }
}

@media (max-width: 575.98px) {
    .facts__cell + .facts__cell {
        border-left: 0;
        border-top: 1px solid var(--crm-border);
    }

    .deal-view__actions {
        width: 100%;
    }
}
</style>
