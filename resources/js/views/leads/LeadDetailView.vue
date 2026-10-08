<script setup>
import { computed, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import { leadsApi } from '@/api/leads';
import { leadSourceMeta, leadStatusMeta } from '@/constants';
import { useToastStore } from '@/stores/toast';
import { parseApiError } from '@/utils/errors';
import { formatDate, money } from '@/utils/format';
import DeleteConfirmModal from '@/components/DeleteConfirmModal.vue';
import ConvertLeadModal from '@/components/ConvertLeadModal.vue';
import LoadingBlock from '@/components/LoadingBlock.vue';
import ScoreBar from '@/components/ScoreBar.vue';
import StatusBadge from '@/components/StatusBadge.vue';

const props = defineProps({ id: { type: String, required: true } });

const router = useRouter();
const toast = useToastStore();

/* -------------------------------------------------------------------------- */
/* State                                                                      */
/* -------------------------------------------------------------------------- */

const lead = ref(null);
const busy = ref(false);
const confirmingDelete = ref(false);
const converting = ref(false);
const convertErrors = ref({});

watch(
    () => props.id,
    async (id) => {
        lead.value = null;
        try {
            lead.value = await leadsApi.get(id);
        } catch {
            toast.error('Lead not found.');
            router.replace({ name: 'leads.index' });
        }
    },
    { immediate: true },
);

/* -------------------------------------------------------------------------- */
/* Presentation (all derived from `lead`, so the template stays declarative)  */
/* -------------------------------------------------------------------------- */

const dash = (value) => value || '—';
const date = (value) => (value ? formatDate(value, true) : '—');

const initials = computed(() =>
    (lead.value?.full_name ?? '')
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0].toUpperCase())
        .join(''),
);

const subtitle = computed(() => {
    const { job_title: title, company } = lead.value;
    if (title && company) return `${title} at ${company}`;
    return title || company || 'No company details';
});

// Add a row here and it renders; no template changes needed.
const contactRows = computed(() => [
    { key: 'name', icon: 'bi-person', label: 'Full name', value: lead.value.full_name },
    { key: 'email', icon: 'bi-envelope', label: 'Email', value: lead.value.email, href: `mailto:${lead.value.email}` },
    {
        key: 'phone',
        icon: 'bi-telephone',
        label: 'Phone',
        value: lead.value.phone,
        href: lead.value.phone ? `tel:${lead.value.phone}` : null,
    },
    { key: 'company', icon: 'bi-building', label: 'Company', value: lead.value.company },
    { key: 'job_title', icon: 'bi-briefcase', label: 'Job title', value: lead.value.job_title },
]);

// Fields in the "Lead information" panel. `kind: 'status'` renders the badge.
const detailRows = computed(() => [
    { key: 'first_name', label: 'First name', value: dash(lead.value.first_name) },
    { key: 'last_name', label: 'Last name', value: dash(lead.value.last_name) },
    { key: 'status', label: 'Status', kind: 'status', value: lead.value.status },
    { key: 'conversion', label: 'Conversion', value: lead.value.is_converted ? 'Converted' : 'Not converted' },
    { key: 'currency', label: 'Currency', value: dash(lead.value.currency) },
    ...(lead.value.updated_at ? [{ key: 'updated', label: 'Last updated', value: date(lead.value.updated_at) }] : []),
]);

const activityRows = computed(() => [
    { key: 'created', label: 'Created', value: date(lead.value.created_at) },
    { key: 'contacted', label: 'Last contacted', value: date(lead.value.last_contacted_at) },
    ...(lead.value.converted_at ? [{ key: 'converted', label: 'Converted', value: date(lead.value.converted_at) }] : []),
]);

const sourceLabel = computed(() => leadSourceMeta[lead.value.source]?.label ?? lead.value.source);
const valueLabel = computed(() => (lead.value.estimated_value ? money(lead.value.estimated_value, lead.value.currency) : '—'));

/* -------------------------------------------------------------------------- */
/* Actions                                                                    */
/* -------------------------------------------------------------------------- */

/** Runs a mutation with a shared busy flag and uniform error toast. */
async function run(action) {
    busy.value = true;
    try {
        await action();
    } catch (e) {
        toast.error(parseApiError(e).message);
        return false;
    } finally {
        busy.value = false;
    }
    return true;
}

const moveTo = (status) =>
    run(async () => {
        lead.value = await leadsApi.changeStatus(props.id, status);
        toast.success(`Lead marked ${leadStatusMeta[status].label.toLowerCase()}.`);
    });

async function convert(payload) {
    convertErrors.value = {};
    busy.value = true;
    try {
        const result = await leadsApi.convert(props.id, payload);
        lead.value = result.lead;
        converting.value = false;
        toast.success(result.contact_created ? 'Lead converted to a new contact.' : 'Lead linked to an existing contact.');
        router.push({ name: 'contacts.show', params: { id: result.contact.id } });
    } catch (e) {
        const parsed = parseApiError(e);
        convertErrors.value = parsed.fields;
        toast.error(parsed.message);
    } finally {
        busy.value = false;
    }
}

async function remove() {
    const ok = await run(async () => {
        await leadsApi.remove(props.id);
        toast.success('Lead deleted.');
    });
    if (ok) router.push({ name: 'leads.index' });
    else confirmingDelete.value = false;
}
</script>

<template>
    <LoadingBlock v-if="!lead" />

    <div v-else class="lead-view">
        <!-- Breadcrumb -->
        <nav class="lead-view__crumbs" aria-label="Breadcrumb">
            <RouterLink :to="{ name: 'leads.index' }">Leads</RouterLink>
            <i class="bi bi-chevron-right" aria-hidden="true"></i>
            <span aria-current="page">{{ lead.full_name }}</span>
        </nav>

        <!-- Header -->
        <header class="lead-view__header">
            <div class="lead-view__identity">
                <div class="avatar" aria-hidden="true">{{ initials }}</div>
                <div class="min-w-0">
                    <div class="d-flex align-items-center flex-wrap gap-2">
                        <h1 class="lead-view__name">{{ lead.full_name }}</h1>
                        <StatusBadge kind="lead" :value="lead.status" />
                    </div>
                    <p class="lead-view__subtitle">{{ subtitle }}</p>
                </div>
            </div>

            <div class="lead-view__actions">
                <template v-if="!lead.is_converted">
                    <RouterLink :to="{ name: 'leads.edit', params: { id } }" class="btn btn-outline-secondary">
                        <i class="bi bi-pencil me-2"></i>Edit
                    </RouterLink>
                    <button type="button" class="btn btn-outline-danger" :disabled="busy" @click="confirmingDelete = true">
                        <i class="bi bi-trash me-2"></i>Delete
                    </button>
                    <button v-if="lead.can_convert" type="button" class="btn btn-success" :disabled="busy" @click="converting = true">
                        <i class="bi bi-arrow-right-circle me-2"></i>Convert
                    </button>
                </template>
                <RouterLink
                    v-else-if="lead.converted_contact_id"
                    :to="{ name: 'contacts.show', params: { id: lead.converted_contact_id } }"
                    class="btn btn-outline-success"
                >
                    <i class="bi bi-person-check me-2"></i>View contact
                </RouterLink>
            </div>
        </header>

        <!-- Key facts -->
        <section class="panel facts" aria-label="Key facts">
            <div class="facts__cell">
                <span class="field-label">Estimated value</span>
                <strong class="facts__value">{{ valueLabel }}</strong>
            </div>
            <div class="facts__cell">
                <span class="field-label">Source</span>
                <strong class="facts__value">{{ sourceLabel }}</strong>
            </div>
            <div class="facts__cell">
                <span class="field-label">Score</span>
                <ScoreBar :value="lead.score" />
            </div>
            <div class="facts__cell">
                <span class="field-label">Owner</span>
                <strong class="facts__value">{{ lead.owner?.name ?? 'Unassigned' }}</strong>
            </div>
        </section>

        <!-- Stage actions -->
        <section v-if="!lead.is_converted && lead.allowed_transitions?.length" class="panel stage" aria-label="Change status">
            <span class="stage__label">Move to</span>
            <div class="stage__buttons">
                <button
                    v-for="s in lead.allowed_transitions"
                    :key="s"
                    type="button"
                    class="btn btn-sm"
                    :class="`btn-outline-${leadStatusMeta[s].color}`"
                    :disabled="busy"
                    @click="moveTo(s)"
                >
                    {{ leadStatusMeta[s].label }}
                </button>
            </div>
        </section>

        <!-- Body -->
        <div class="lead-view__grid">
            <main class="lead-view__main">
                <section class="panel">
                    <header class="panel__head"><h2 class="panel__title">Lead information</h2></header>
                    <dl class="info">
                        <div v-for="row in detailRows" :key="row.key" class="info__item">
                            <dt class="field-label">{{ row.label }}</dt>
                            <dd class="info__value">
                                <StatusBadge v-if="row.kind === 'status'" kind="lead" :value="row.value" />
                                <template v-else>{{ row.value }}</template>
                            </dd>
                        </div>
                    </dl>
                </section>

                <section class="panel">
                    <header class="panel__head"><h2 class="panel__title">Notes</h2></header>
                    <div class="panel__body">
                        <p v-if="lead.notes" class="notes">{{ lead.notes }}</p>
                        <p v-else class="empty">No notes yet. Add context by editing this lead.</p>
                    </div>
                </section>
            </main>

            <aside class="lead-view__side">
                <section class="panel">
                    <header class="panel__head"><h2 class="panel__title">Contact details</h2></header>
                    <ul class="rows">
                        <li v-for="row in contactRows" :key="row.key" class="rows__item">
                            <i class="bi rows__icon" :class="row.icon" aria-hidden="true"></i>
                            <div class="min-w-0">
                                <span class="field-label">{{ row.label }}</span>
                                <a v-if="row.href && row.value" :href="row.href" class="rows__value rows__link">{{ row.value }}</a>
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

        <ConvertLeadModal :show="converting" :lead="lead" :loading="busy" :errors="convertErrors" @confirm="convert" @cancel="converting = false" />

        <DeleteConfirmModal
            :show="confirmingDelete"
            title="Delete lead"
            :message="`Delete ${lead.full_name}?`"
            :loading="busy"
            @confirm="remove"
            @cancel="confirmingDelete = false"
        />
    </div>
</template>

<style scoped>
/* Tokens: change here and the whole page follows. */
.lead-view {
    --lv-font: 'Inter', system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
    --lv-fs: 0.875rem; /* 14px body */
    --lv-fs-sm: 0.8125rem; /* 13px labels */
    --lv-text: #101828;
    --lv-text-strong: #344054;
    --lv-muted: #667085;
    --lv-border: #e4e7ec;
    --lv-surface: #fff;
    --lv-soft: #f9fafb;
    --lv-primary: var(--bs-primary, #2563eb);
    --lv-radius: 0.75rem;
    --lv-gap: 1.25rem;

    display: flex;
    flex-direction: column;
    gap: var(--lv-gap);
    font-family: var(--lv-font);
    font-size: var(--lv-fs);
    line-height: 1.5;
    color: var(--lv-text);
    -webkit-font-smoothing: antialiased;
}

.min-w-0 {
    min-width: 0;
}

/* Breadcrumb */
.lead-view__crumbs {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: var(--lv-fs-sm);
    color: var(--lv-muted);
}

.lead-view__crumbs a {
    color: inherit;
    text-decoration: none;
}

.lead-view__crumbs a:hover {
    color: var(--lv-primary);
}

.lead-view__crumbs i {
    font-size: 0.625rem;
}

.lead-view__crumbs [aria-current] {
    color: var(--lv-text-strong);
    font-weight: 500;
}

/* Header */
.lead-view__header {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
}

.lead-view__identity {
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
    color: var(--lv-primary);
    background: color-mix(in srgb, var(--lv-primary) 10%, #fff);
    border-radius: 50%;
}

.lead-view__name {
    margin: 0;
    font-size: 1.5rem;
    font-weight: 600;
    letter-spacing: -0.02em;
    line-height: 1.25;
}

.lead-view__subtitle {
    margin: 0.125rem 0 0;
    color: var(--lv-muted);
}

.lead-view__actions {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.lead-view__actions .btn {
    --bs-btn-font-size: var(--lv-fs);
    --bs-btn-font-weight: 500;
    --bs-btn-border-radius: 0.5rem;
    display: inline-flex;
    align-items: center;
    min-height: 2.25rem;
}

/* Panel: the one container pattern used across the page */
.panel {
    background: var(--lv-surface);
    border: 1px solid var(--lv-border);
    border-radius: var(--lv-radius);
}

.panel__head {
    padding: 0.875rem 1.25rem;
    border-bottom: 1px solid var(--lv-border);
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
    font-size: var(--lv-fs-sm);
    font-weight: 500;
    color: var(--lv-muted);
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
    border-left: 1px solid var(--lv-border);
}

.facts__value {
    font-size: 1.125rem;
    font-weight: 600;
    font-variant-numeric: tabular-nums;
    line-height: 1.3;
}

/* Stage actions */
.stage {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem 1.25rem;
    background: var(--lv-soft);
}

.stage__label {
    font-size: var(--lv-fs-sm);
    font-weight: 500;
    color: var(--lv-muted);
}

.stage__buttons {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}

/* Layout */
.lead-view__grid {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 20rem;
    gap: var(--lv-gap);
    align-items: start;
}

.lead-view__main,
.lead-view__side {
    display: flex;
    flex-direction: column;
    gap: var(--lv-gap);
    min-width: 0;
}

/* Lead information grid */
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

/* Notes */
.notes {
    margin: 0;
    max-width: 70ch;
    color: var(--lv-text-strong);
    white-space: pre-wrap;
    overflow-wrap: anywhere;
}

.empty {
    margin: 0;
    color: var(--lv-muted);
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
    color: var(--lv-muted);
}

.rows__value {
    display: block;
    overflow-wrap: anywhere;
}

.rows__link {
    color: var(--lv-text);
    text-decoration: none;
}

.rows__link:hover {
    color: var(--lv-primary);
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
    color: var(--lv-muted);
}

.activity__item dd {
    margin: 0;
    font-weight: 500;
    text-align: right;
    font-variant-numeric: tabular-nums;
}

/* Responsive */
@media (max-width: 991.98px) {
    .lead-view__grid {
        grid-template-columns: minmax(0, 1fr);
    }
}

@media (max-width: 575.98px) {
    .facts__cell + .facts__cell {
        border-left: 0;
        border-top: 1px solid var(--lv-border);
    }

    .lead-view__actions {
        width: 100%;
    }
}
</style>