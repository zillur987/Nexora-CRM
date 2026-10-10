<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref, unref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { companiesApi } from '@/api/companies';
import { contactLookupsApi } from '@/api/contacts';
import { dealLookupsApi, dealsApi } from '@/api/deals';
import { usersApi } from '@/api/users';
import { useColumnVisibility } from '@/composables/useColumnVisibility';
import { useListQuery } from '@/composables/useListQuery';
import { useToastStore } from '@/stores/toast';
import { downloadCsv } from '@/utils/csv';
import { DEAL_CSV_COLUMNS, dealToCsvRow } from '@/utils/dealCsv';
import { OUTCOMES, PRIORITIES, priorityMeta, stageBadge } from '@/utils/dealOptions';
import { formatDate } from '@/utils/format';
import { formatDateOnly } from '@/utils/dateOnly';
import { parseApiError } from '@/utils/errors';
import { formatMoney } from '@/utils/money';
import ConfirmModal from '@/components/ConfirmModal.vue';
import DeleteConfirmModal from '@/components/DeleteConfirmModal.vue';
import LoadingBlock from '@/components/LoadingBlock.vue';
import Pagination from '@/components/Pagination.vue';
import RecordDrawer from '@/components/RecordDrawer.vue';
import DealFormView from '@/views/deals/DealFormView.vue';

const toast = useToastStore();
const route = useRoute();
const router = useRouter();

/* -------------------------------------------------------------------------- */
/* Static config                                                              */
/* -------------------------------------------------------------------------- */

const DEFAULT_FILTERS = {
    search: '',
    deal_stage_id: '',
    deal_type_id: '',
    lead_source_id: '',
    company_id: '',
    owner_id: '',
    priority: '',
    outcome: '',
    close_from: '',
    close_to: '',
    sort_by: 'created_at',
    sort_dir: 'desc',
};

const SORT_OPTIONS = [
    { value: 'created_at', label: 'Created' },
    { value: 'name', label: 'Deal name' },
    { value: 'amount', label: 'Amount' },
    { value: 'expected_close_date', label: 'Expected close' },
    { value: 'probability', label: 'Probability' },
];

// Order here = order of table columns and of the Columns dropdown.
const COLUMNS = [
    { key: 'id', label: 'Id', default: true },
    { key: 'name', label: 'Deal name', locked: true, default: true },
    { key: 'owner', label: 'Deal owner', default: true },
    { key: 'company', label: 'Company', default: true },
    { key: 'contact', label: 'Contact', default: true },
    { key: 'amount', label: 'Amount', default: true },
    { key: 'stage', label: 'Deal stage', default: true },
    { key: 'close', label: 'Expected close', default: true },
    { key: 'priority', label: 'Priority', default: false },
    { key: 'probability', label: 'Probability', default: false },
    { key: 'weighted', label: 'Weighted amount', default: false },
    { key: 'type', label: 'Deal type', default: false },
    { key: 'source', label: 'Lead source', default: false },
    { key: 'next_step', label: 'Next step', default: false },
    { key: 'created_at', label: 'Created', default: false },
];

const { visible: visibleColumns, shown: shownColumns } = useColumnVisibility('deals.visibleColumns.v1', COLUMNS);

/* -------------------------------------------------------------------------- */
/* Data                                                                       */
/* -------------------------------------------------------------------------- */

const { filters, items, meta, loading, error, load, goToPage, reset } = useListQuery(dealsApi.list, {
    ...DEFAULT_FILTERS,
});

const summary = ref({ total: 0, by_stage: {}, totals: [] });
const owners = ref([]);
const sources = ref([]);
const types = ref([]);
const stages = ref([]);
const companies = ref([]);

async function loadSummary() {
    try {
        summary.value = await dealsApi.summary();
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

const sortByName = (a, b) => a.name.localeCompare(b.name);

/** The form creates stages / types / sources on the fly; keep the filter + tabs in sync. */
function onLookupCreated({ kind, item }) {
    const target = { 'deal-stages': stages, 'deal-types': types, 'contact-sources': sources }[kind];
    if (!target || target.value.some((existing) => existing.id === item.id)) return;

    // Stages keep pipeline order (new ones are appended); the other lists are alphabetical.
    target.value = kind === 'deal-stages' ? [...target.value, item] : [...target.value, item].sort(sortByName);
}

async function loadCompanies() {
    try {
        companies.value = await companiesApi.options();
    } catch {
        /* the company filter just stays empty */
    }
}

/* -------------------------------------------------------------------------- */
/* KPI strip: pipeline value per currency (amounts are never summed across currencies) */
/* -------------------------------------------------------------------------- */

const kpis = computed(() => {
    const totals = summary.value.totals ?? [];
    const money = (field) => totals.filter((t) => t[field] > 0).map((t) => ({ currency: t.currency, text: formatMoney(t[field], t.currency, { compact: true }) }));
    const openCount = totals.reduce((sum, t) => sum + t.open_count, 0);

    return [
        { key: 'open', label: 'Open pipeline', values: money('open_amount'), hint: `${openCount} open ${openCount === 1 ? 'deal' : 'deals'}` },
        { key: 'weighted', label: 'Weighted forecast', values: money('weighted_amount'), hint: 'Amount × probability' },
        { key: 'won', label: 'Won', values: money('won_amount'), hint: 'Closed won, all time' },
    ];
});

/* -------------------------------------------------------------------------- */
/* Filter panel + columns dropdown                                            */
/* -------------------------------------------------------------------------- */

const filterOpen = ref(false);
const filterRef = ref(null);
const columnsOpen = ref(false);
const columnsRef = ref(null);

const PANEL_FILTERS = ['deal_type_id', 'lead_source_id', 'company_id', 'owner_id', 'priority', 'outcome', 'close_from', 'close_to'];

// Counts only what lives inside the panel (search and the stage tabs have their own controls).
const activeFilterCount = computed(() => {
    const f = unref(filters);
    const fields = PANEL_FILTERS.filter((key) => f[key] !== '').length;
    const sorted = f.sort_by !== DEFAULT_FILTERS.sort_by || f.sort_dir !== DEFAULT_FILTERS.sort_dir;
    return fields + (sorted ? 1 : 0);
});

/* -------------------------------------------------------------------------- */
/* Row actions menu (teleported so the scrolling table can't clip it)         */
/* -------------------------------------------------------------------------- */

const menu = ref({ deal: null, top: 0, right: 0 });

function closeMenu() {
    menu.value = { deal: null, top: 0, right: 0 };
}

function toggleMenu(event, deal) {
    if (menu.value.deal?.id === deal.id) return closeMenu();

    const rect = event.currentTarget.getBoundingClientRect();
    menu.value = { deal, top: rect.bottom + 4, right: window.innerWidth - rect.right };
}

/* -------------------------------------------------------------------------- */
/* Delete                                                                     */
/* -------------------------------------------------------------------------- */

const toDelete = ref(null);
const deleting = ref(false);

function askDelete(deal) {
    closeMenu();
    toDelete.value = deal;
}

async function confirmDelete() {
    deleting.value = true;
    try {
        const wasLastRowOnPage = items.value.length === 1;
        const page = unref(meta)?.current_page ?? 1;

        await dealsApi.remove(toDelete.value.id);
        toast.success('Deal deleted.');
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
        `deals-${new Date().toISOString().slice(0, 10)}.csv`,
        DEAL_CSV_COLUMNS,
        items.value.map(dealToCsvRow),
    );
}

/* -------------------------------------------------------------------------- */
/* Create / edit drawer (driven by the route, so it is linkable and survives refresh) */
/* -------------------------------------------------------------------------- */

const drawer = reactive({ show: false, dealId: null });
const formRef = ref(null);
const confirmDiscard = ref(false);

watch(
    () => [route.name, route.params.id],
    ([name, id]) => {
        drawer.show = name === 'deals.create' || name === 'deals.edit';
        if (drawer.show) drawer.dealId = name === 'deals.edit' ? String(id) : null;
    },
    { immediate: true },
);

function closeDrawer() {
    confirmDiscard.value = false;
    router.replace({ name: 'deals.index', query: route.query });
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
async function onDealSaved(saved, { another } = {}) {
    await refresh();
    if (!another) closeDrawer();
}

/* -------------------------------------------------------------------------- */
/* Global listeners                                                           */
/* -------------------------------------------------------------------------- */

function onDocumentClick(event) {
    const target = event.target;
    if (filterOpen.value && !filterRef.value?.contains(target)) filterOpen.value = false;
    if (columnsOpen.value && !columnsRef.value?.contains(target)) columnsOpen.value = false;
    if (menu.value.deal && !target.closest?.('.row-menu, .row-menu-toggle')) closeMenu();
}

function onKeydown(event) {
    if (event.key !== 'Escape') return;
    filterOpen.value = false;
    columnsOpen.value = false;
    closeMenu();
}

onMounted(async () => {
    document.addEventListener('click', onDocumentClick);
    document.addEventListener('keydown', onKeydown);
    window.addEventListener('resize', closeMenu);
    window.addEventListener('scroll', closeMenu, true);

    loadSummary();

    // Independent lookups: one failing must not blank the others.
    const [ownerList, sourceList, typeList, stageList] = await Promise.allSettled([
        usersApi.options(),
        contactLookupsApi.list('contact-sources'),
        dealLookupsApi.list('deal-types'),
        dealLookupsApi.list('deal-stages'),
        loadCompanies(),
    ]);
    if (ownerList.status === 'fulfilled') owners.value = ownerList.value;
    if (sourceList.status === 'fulfilled') sources.value = sourceList.value;
    if (typeList.status === 'fulfilled') types.value = typeList.value;
    if (stageList.status === 'fulfilled') stages.value = stageList.value;
});

onBeforeUnmount(() => {
    document.removeEventListener('click', onDocumentClick);
    document.removeEventListener('keydown', onKeydown);
    window.removeEventListener('resize', closeMenu);
    window.removeEventListener('scroll', closeMenu, true);
});
</script>

<template>
    <div class="card deals-page rounded-3 overflow-visible">
        <!-- Header -->
        <div class="card-header bg-white d-flex justify-content-between align-items-center px-3 py-3 rounded-top-3">
            <h1 class="deals-title mb-0">Deals</h1>
            <RouterLink :to="{ name: 'deals.create' }" class="btn btn-primary no-print">
                <i class="bi bi-plus-lg me-2"></i>Create Deal
            </RouterLink>
        </div>

        <!-- Pipeline KPIs -->
        <section class="deals-kpis" aria-label="Pipeline summary">
            <div v-for="k in kpis" :key="k.key" class="deals-kpi">
                <span class="deals-kpi__label">{{ k.label }}</span>
                <strong class="deals-kpi__value">
                    <template v-if="k.values.length">
                        <span v-for="v in k.values" :key="v.currency" class="deals-kpi__amount">{{ v.text }}</span>
                    </template>
                    <template v-else>—</template>
                </strong>
                <span class="deals-kpi__hint">{{ k.hint }}</span>
            </div>
        </section>

        <!-- Stage tabs -->
        <ul class="nav nav-pills flex-nowrap overflow-auto gap-1 px-3 pt-3 pb-3 no-print">
            <li class="nav-item">
                <button type="button" class="nav-link text-nowrap" :class="{ active: filters.deal_stage_id === '' }" @click="filters.deal_stage_id = ''">
                    All
                    <span class="badge rounded-pill ms-1" :class="filters.deal_stage_id === '' ? 'text-bg-light' : 'text-bg-secondary'">
                        {{ summary.total }}
                    </span>
                </button>
            </li>
            <li v-for="s in stages" :key="s.id" class="nav-item">
                <button
                    type="button"
                    class="nav-link text-nowrap"
                    :class="{ active: filters.deal_stage_id === String(s.id) }"
                    @click="filters.deal_stage_id = String(s.id)"
                >
                    {{ s.name }}
                    <span
                        class="badge rounded-pill ms-1"
                        :class="filters.deal_stage_id === String(s.id) ? 'text-bg-light' : 'text-bg-secondary'"
                    >
                        {{ summary.by_stage[s.id] ?? 0 }}
                    </span>
                </button>
            </li>
        </ul>

        <!-- Toolbar -->
        <div class="deals-toolbar d-flex flex-wrap align-items-center gap-2 px-3 py-3 bg-light border-top border-bottom no-print">
            <form class="input-group deals-search" role="search" @submit.prevent="load">
                <input v-model.trim="filters.search" type="search" class="form-control" placeholder="Search" aria-label="Search deals" />
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

                <div v-if="filterOpen" class="card shadow filter-panel" role="dialog" aria-label="Filter deals">
                    <div class="card-body d-flex flex-column gap-3">
                        <div>
                            <label for="deal-filter-outcome" class="form-label small text-muted mb-1">Status</label>
                            <select id="deal-filter-outcome" v-model="filters.outcome" class="form-select">
                                <option value="">All</option>
                                <option v-for="o in OUTCOMES" :key="o.value" :value="o.value">{{ o.label }}</option>
                            </select>
                        </div>

                        <div>
                            <label for="deal-filter-company" class="form-label small text-muted mb-1">Company</label>
                            <select id="deal-filter-company" v-model="filters.company_id" class="form-select">
                                <option value="">All</option>
                                <option v-for="c in companies" :key="c.id" :value="String(c.id)">{{ c.name }}</option>
                            </select>
                        </div>

                        <div>
                            <label for="deal-filter-type" class="form-label small text-muted mb-1">Deal type</label>
                            <select id="deal-filter-type" v-model="filters.deal_type_id" class="form-select">
                                <option value="">All</option>
                                <option v-for="t in types" :key="t.id" :value="String(t.id)">{{ t.name }}</option>
                            </select>
                        </div>

                        <div>
                            <label for="deal-filter-source" class="form-label small text-muted mb-1">Lead source</label>
                            <select id="deal-filter-source" v-model="filters.lead_source_id" class="form-select">
                                <option value="">All</option>
                                <option v-for="s in sources" :key="s.id" :value="String(s.id)">{{ s.name }}</option>
                            </select>
                        </div>

                        <div class="row g-2">
                            <div class="col-6">
                                <label for="deal-filter-owner" class="form-label small text-muted mb-1">Owner</label>
                                <select id="deal-filter-owner" v-model="filters.owner_id" class="form-select">
                                    <option value="">Anyone</option>
                                    <option value="unassigned">Unassigned</option>
                                    <option v-for="o in owners" :key="o.id" :value="String(o.id)">{{ o.name }}</option>
                                </select>
                            </div>
                            <div class="col-6">
                                <label for="deal-filter-priority" class="form-label small text-muted mb-1">Priority</label>
                                <select id="deal-filter-priority" v-model="filters.priority" class="form-select">
                                    <option value="">Any</option>
                                    <option v-for="p in PRIORITIES" :key="p.value" :value="p.value">{{ p.label }}</option>
                                </select>
                            </div>
                        </div>

                        <div class="row g-2">
                            <div class="col-6">
                                <label for="deal-filter-close-from" class="form-label small text-muted mb-1">Close date from</label>
                                <input id="deal-filter-close-from" v-model="filters.close_from" type="date" class="form-control" :max="filters.close_to || null" />
                            </div>
                            <div class="col-6">
                                <label for="deal-filter-close-to" class="form-label small text-muted mb-1">Close date to</label>
                                <input id="deal-filter-close-to" v-model="filters.close_to" type="date" class="form-control" :min="filters.close_from || null" />
                            </div>
                        </div>

                        <div class="row g-2">
                            <div class="col-7">
                                <label for="deal-filter-sort" class="form-label small text-muted mb-1">Sort by</label>
                                <select id="deal-filter-sort" v-model="filters.sort_by" class="form-select">
                                    <option v-for="o in SORT_OPTIONS" :key="o.value" :value="o.value">{{ o.label }}</option>
                                </select>
                            </div>
                            <div class="col-5">
                                <label for="deal-filter-order" class="form-label small text-muted mb-1">Order</label>
                                <select id="deal-filter-order" v-model="filters.sort_dir" class="form-select">
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
                                :id="`deal-col-${c.key}`"
                                v-model="visibleColumns"
                                type="checkbox"
                                class="form-check-input"
                                :value="c.key"
                                :disabled="c.locked"
                            />
                            <label :for="`deal-col-${c.key}`" class="form-check-label w-100 text-nowrap">{{ c.label }}</label>
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

        <div v-else class="table-responsive deals-scroll" :style="{ opacity: loading ? 0.6 : 1 }">
            <table class="table align-middle mb-0 deals-table">
                <thead>
                    <tr>
                        <th v-for="c in shownColumns" :key="c.key" scope="col" :class="{ 'text-end': c.key === 'amount' || c.key === 'weighted' }">{{ c.label }}</th>
                        <th scope="col" class="no-print"><span class="visually-hidden">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="!items.length">
                        <td :colspan="shownColumns.length + 1" class="text-center text-muted py-5">No deals found.</td>
                    </tr>

                    <tr v-for="(d, index) in items" :key="d.id">
                        <td v-for="col in shownColumns" :key="col.key" :class="{ 'text-end': col.key === 'amount' || col.key === 'weighted' }">
                            <template v-if="col.key === 'id'">{{ rowOffset + index + 1 }}</template>

                            <RouterLink v-else-if="col.key === 'name'" :to="{ name: 'deals.show', params: { id: d.id } }" class="deal-link">
                                {{ d.name }}
                            </RouterLink>

                            <template v-else-if="col.key === 'owner'">{{ d.owner?.name ?? '—' }}</template>

                            <span v-else-if="col.key === 'company'">
                                <RouterLink v-if="d.company" :to="{ name: 'companies.show', params: { id: d.company.id } }" class="deal-link">
                                    {{ d.company.name }}
                                </RouterLink>
                                <template v-else>—</template>
                            </span>

                            <span v-else-if="col.key === 'contact'">
                                <RouterLink v-if="d.contact" :to="{ name: 'contacts.show', params: { id: d.contact.id } }" class="deal-link">
                                    {{ d.contact.name }}
                                </RouterLink>
                                <template v-else>—</template>
                            </span>

                            <template v-else-if="col.key === 'amount'">{{ formatMoney(d.amount, d.currency) }}</template>

                            <span v-else-if="col.key === 'stage'">
                                <span v-if="d.stage" class="badge" :class="stageBadge(d.stage)">{{ d.stage.name }}</span>
                                <template v-else>—</template>
                            </span>

                            <span v-else-if="col.key === 'close'" :class="{ 'text-danger': d.is_overdue }" :title="d.is_overdue ? 'Overdue' : null">
                                {{ formatDateOnly(d.expected_close_date) }}
                            </span>

                            <span v-else-if="col.key === 'priority'">
                                <span v-if="priorityMeta(d.priority)" class="badge" :class="priorityMeta(d.priority).badge">{{ priorityMeta(d.priority).label }}</span>
                                <template v-else>—</template>
                            </span>

                            <template v-else-if="col.key === 'probability'">{{ d.probability === null ? '—' : `${d.probability}%` }}</template>
                            <template v-else-if="col.key === 'weighted'">{{ formatMoney(d.weighted_amount, d.currency) }}</template>
                            <template v-else-if="col.key === 'type'">{{ d.type?.name ?? '—' }}</template>
                            <template v-else-if="col.key === 'source'">{{ d.lead_source?.name ?? '—' }}</template>
                            <template v-else-if="col.key === 'next_step'">{{ d.next_step ?? '—' }}</template>
                            <template v-else-if="col.key === 'created_at'">{{ formatDate(d.created_at) }}</template>
                        </td>

                        <td class="text-end no-print">
                            <button
                                type="button"
                                class="btn btn-sm btn-link text-body row-menu-toggle"
                                aria-haspopup="menu"
                                :aria-expanded="menu.deal?.id === d.id"
                                :aria-label="`Actions for ${d.name}`"
                                @click="toggleMenu($event, d)"
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
    <RecordDrawer :show="drawer.show" :title="drawer.dealId ? 'Edit Deal' : 'Create Deal'" @close="requestClose">
        <DealFormView
            ref="formRef"
            :key="route.fullPath"
            :deal-id="drawer.dealId"
            :owners="owners"
            :stages="stages"
            :types="types"
            :sources="sources"
            @saved="onDealSaved"
            @lookup-created="onLookupCreated"
            @cancel="closeDrawer"
        />
    </RecordDrawer>

    <!-- Row actions menu -->
    <Teleport to="body">
        <ul v-if="menu.deal" class="dropdown-menu show row-menu shadow" role="menu" :style="{ top: `${menu.top}px`, right: `${menu.right}px` }">
            <li>
                <RouterLink class="dropdown-item" role="menuitem" :to="{ name: 'deals.show', params: { id: menu.deal.id } }" @click="closeMenu">
                    <i class="bi bi-eye me-2"></i>View
                </RouterLink>
            </li>
            <li>
                <RouterLink class="dropdown-item" role="menuitem" :to="{ name: 'deals.edit', params: { id: menu.deal.id } }" @click="closeMenu">
                    <i class="bi bi-pencil me-2"></i>Edit
                </RouterLink>
            </li>
            <li><hr class="dropdown-divider" /></li>
            <li>
                <button type="button" class="dropdown-item text-danger" role="menuitem" @click="askDelete(menu.deal)">
                    <i class="bi bi-trash me-2"></i>Delete
                </button>
            </li>
        </ul>
    </Teleport>

    <DeleteConfirmModal
        :show="!!toDelete"
        title="Delete deal"
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
   so changing a token restyles Leads, Contacts, Companies and every future module together. */
.deals-page {
    font-family: var(--crm-font);
    font-size: var(--crm-fs);
    line-height: 1.5;
    color: var(--crm-text);
}

.deals-title {
    font-size: 1.5rem;
    font-weight: 600;
    letter-spacing: -0.02em;
}

/* Controls: one height, one font size, one radius */
.deals-page .form-control,
.deals-page .form-select {
    min-height: var(--crm-control-h);
    padding-block: 0.375rem;
    font-size: var(--crm-fs);
}

.deals-page .form-label {
    margin-bottom: 0.25rem;
    font-size: var(--crm-fs-sm);
    font-weight: 500;
    color: var(--crm-text-muted);
}

.deals-page .btn:not(.btn-sm) {
    --bs-btn-font-size: var(--crm-fs);
    --bs-btn-font-weight: 500;
    --bs-btn-padding-x: 0.875rem;
    --bs-btn-border-radius: var(--crm-radius);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: var(--crm-control-h);
}

.deals-page .nav-link {
    padding: 0.4rem 0.875rem;
    font-size: var(--crm-fs);
    font-weight: 500;
}

.deals-page .badge {
    font-size: var(--crm-fs-xs);
    font-weight: 500;
}

.deals-page .dropdown-menu,
.row-menu {
    --bs-dropdown-font-size: var(--crm-fs);
    --bs-dropdown-border-radius: var(--crm-radius);
    --bs-dropdown-item-padding-y: 0.4rem;
}

.deals-page :deep(.pagination) {
    --bs-pagination-font-size: var(--crm-fs);
}

/* Pipeline KPIs */
.deals-kpis {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(12rem, 1fr));
    border-bottom: 1px solid var(--crm-border);
}

.deals-kpi {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    padding: 1rem 1.25rem;
}

.deals-kpi + .deals-kpi {
    border-left: 1px solid var(--crm-border);
}

.deals-kpi__label {
    font-size: var(--crm-fs-sm);
    font-weight: 500;
    color: var(--crm-text-muted);
}

.deals-kpi__value {
    display: flex;
    flex-wrap: wrap;
    gap: 0 0.75rem;
    font-size: 1.125rem;
    font-weight: 600;
    font-variant-numeric: tabular-nums;
    line-height: 1.4;
}

.deals-kpi__hint {
    font-size: var(--crm-fs-xs);
    color: var(--crm-text-muted);
}

/* Toolbar */
.deals-search {
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
.deals-table {
    --bs-table-bg: transparent;
    font-size: var(--crm-fs);
    color: var(--crm-text);
    font-variant-numeric: tabular-nums;
}

.deals-table thead th {
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

.deals-table tbody td {
    padding: 0.75rem 1rem;
    white-space: nowrap;
    border-bottom: 1px solid var(--crm-border);
}

.deals-table tbody tr:last-child td {
    border-bottom: 0;
}

.deal-link {
    color: inherit;
    text-decoration: none;
}

.deal-link:hover {
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
    .deals-kpi + .deals-kpi {
        border-top: 1px solid var(--crm-border);
        border-left: 0;
    }
}

@media print {
    .no-print {
        display: none !important;
    }

    .deals-page {
        border: 0 !important;
        box-shadow: none !important;
    }

    .deals-page .table-responsive {
        overflow: visible !important;
        max-height: none !important;
    }
}
</style>
