<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref, unref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { companiesApi } from '@/api/companies';
import { quotesApi } from '@/api/quotes';
import { usersApi } from '@/api/users';
import { useColumnVisibility } from '@/composables/useColumnVisibility';
import { useListQuery } from '@/composables/useListQuery';
import { useToastStore } from '@/stores/toast';
import { downloadCsv } from '@/utils/csv';
import { formatDate } from '@/utils/format';
import { formatDateOnly } from '@/utils/dateOnly';
import { parseApiError } from '@/utils/errors';
import { formatMoney } from '@/utils/money';
import { QUOTE_CSV_COLUMNS, quoteToCsvRow } from '@/utils/quoteCsv';
import { STATUSES, statusBadge, statusMeta } from '@/utils/quoteOptions';
import ConfirmModal from '@/components/ConfirmModal.vue';
import DeleteConfirmModal from '@/components/DeleteConfirmModal.vue';
import LoadingBlock from '@/components/LoadingBlock.vue';
import Pagination from '@/components/Pagination.vue';
import RecordDrawer from '@/components/RecordDrawer.vue';
import QuoteFormView from '@/views/quotes/QuoteFormView.vue';

const toast = useToastStore();
const route = useRoute();
const router = useRouter();

/* -------------------------------------------------------------------------- */
/* Static config                                                              */
/* -------------------------------------------------------------------------- */

const DEFAULT_FILTERS = {
    search: '',
    status: '',
    company_id: '',
    owner_id: '',
    issue_from: '',
    issue_to: '',
    sort_by: 'created_at',
    sort_dir: 'desc',
};

const SORT_OPTIONS = [
    { value: 'created_at', label: 'Created' },
    { value: 'quote_number', label: 'Quote number' },
    { value: 'total', label: 'Total' },
    { value: 'issue_date', label: 'Issue date' },
    { value: 'valid_until', label: 'Valid until' },
];

// Order here = order of table columns and of the Columns dropdown.
const COLUMNS = [
    { key: 'id', label: 'Id', default: true },
    { key: 'number', label: 'Quote number', locked: true, default: true },
    { key: 'subject', label: 'Subject', default: true },
    { key: 'owner', label: 'Quote owner', default: true },
    { key: 'company', label: 'Company', default: true },
    { key: 'contact', label: 'Contact', default: true },
    { key: 'total', label: 'Total', default: true },
    { key: 'status', label: 'Status', default: true },
    { key: 'valid_until', label: 'Valid until', default: true },
    { key: 'deal', label: 'Deal', default: false },
    { key: 'issue_date', label: 'Issue date', default: false },
    { key: 'created_at', label: 'Created', default: false },
];

const { visible: visibleColumns, shown: shownColumns } = useColumnVisibility('quotes.visibleColumns.v1', COLUMNS);

const isRight = (key) => key === 'total';

/* -------------------------------------------------------------------------- */
/* Data                                                                       */
/* -------------------------------------------------------------------------- */

const { filters, items, meta, loading, error, load, goToPage, reset } = useListQuery(quotesApi.list, {
    ...DEFAULT_FILTERS,
});

const summary = ref({ total: 0, by_status: {}, acceptance_rate: null, totals: [] });
const owners = ref([]);
const companies = ref([]);

async function loadSummary() {
    try {
        summary.value = await quotesApi.summary();
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
/* KPI strip: quote value per currency (amounts are never summed across currencies) */
/* -------------------------------------------------------------------------- */

const kpis = computed(() => {
    const totals = summary.value.totals ?? [];
    const money = (field) => totals.filter((t) => t[field] > 0).map((t) => ({ currency: t.currency, text: formatMoney(t[field], t.currency, { compact: true }) }));
    const openCount = totals.reduce((sum, t) => sum + t.open_count, 0);
    const rate = summary.value.acceptance_rate;

    return [
        { key: 'open', label: 'Open quotes', values: money('open_amount'), hint: `${openCount} draft or sent` },
        { key: 'accepted', label: 'Accepted', values: money('accepted_amount'), hint: 'All time' },
        { key: 'rate', label: 'Acceptance rate', values: rate === null ? [] : [{ currency: '', text: `${rate}%` }], hint: 'Accepted ÷ (accepted + rejected)' },
    ];
});

/* -------------------------------------------------------------------------- */
/* Filter panel + columns dropdown                                            */
/* -------------------------------------------------------------------------- */

const filterOpen = ref(false);
const filterRef = ref(null);
const columnsOpen = ref(false);
const columnsRef = ref(null);

const PANEL_FILTERS = ['company_id', 'owner_id', 'issue_from', 'issue_to'];

// Counts only what lives inside the panel (search and the status tabs have their own controls).
const activeFilterCount = computed(() => {
    const f = unref(filters);
    const fields = PANEL_FILTERS.filter((key) => f[key] !== '').length;
    const sorted = f.sort_by !== DEFAULT_FILTERS.sort_by || f.sort_dir !== DEFAULT_FILTERS.sort_dir;
    return fields + (sorted ? 1 : 0);
});

async function loadLookups() {
    // Independent lookups: one failing must not blank the other.
    const [ownerList, companyList] = await Promise.allSettled([usersApi.options(), companiesApi.options()]);
    if (ownerList.status === 'fulfilled') owners.value = ownerList.value;
    if (companyList.status === 'fulfilled') companies.value = companyList.value;
}

/* -------------------------------------------------------------------------- */
/* Row actions menu (teleported so the scrolling table can't clip it)         */
/* -------------------------------------------------------------------------- */

const menu = ref({ quote: null, top: 0, right: 0 });

function closeMenu() {
    menu.value = { quote: null, top: 0, right: 0 };
}

function toggleMenu(event, quote) {
    if (menu.value.quote?.id === quote.id) return closeMenu();

    const rect = event.currentTarget.getBoundingClientRect();
    menu.value = { quote, top: rect.bottom + 4, right: window.innerWidth - rect.right };
}

/* -------------------------------------------------------------------------- */
/* Delete                                                                     */
/* -------------------------------------------------------------------------- */

const toDelete = ref(null);
const deleting = ref(false);

function askDelete(quote) {
    closeMenu();
    toDelete.value = quote;
}

async function confirmDelete() {
    deleting.value = true;
    try {
        const wasLastRowOnPage = items.value.length === 1;
        const page = unref(meta)?.current_page ?? 1;

        await quotesApi.remove(toDelete.value.id);
        toast.success('Quote deleted.');
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
        `quotes-${new Date().toISOString().slice(0, 10)}.csv`,
        QUOTE_CSV_COLUMNS,
        items.value.map(quoteToCsvRow),
    );
}

/* -------------------------------------------------------------------------- */
/* Create / edit drawer (driven by the route, so it is linkable and survives refresh) */
/* -------------------------------------------------------------------------- */

const drawer = reactive({ show: false, quoteId: null });
const formRef = ref(null);
const confirmDiscard = ref(false);

watch(
    () => [route.name, route.params.id],
    ([name, id]) => {
        drawer.show = name === 'quotes.create' || name === 'quotes.edit';
        if (drawer.show) drawer.quoteId = name === 'quotes.edit' ? String(id) : null;
    },
    { immediate: true },
);

// "New quote from a deal" arrives as /quotes/new?deal_id=12
const prefillDealId = computed(() => (route.name === 'quotes.create' && route.query.deal_id ? String(route.query.deal_id) : null));

function closeDrawer() {
    confirmDiscard.value = false;
    const { deal_id, ...query } = route.query; // the prefill belongs to the drawer, not to the list
    router.replace({ name: 'quotes.index', query });
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
async function onQuoteSaved(saved, { another } = {}) {
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
    if (menu.value.quote && !target.closest?.('.row-menu, .row-menu-toggle')) closeMenu();
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
    <div class="card quotes-page rounded-3 overflow-visible">
        <!-- Header -->
        <div class="card-header bg-white d-flex justify-content-between align-items-center px-3 py-3 rounded-top-3">
            <h1 class="quotes-title mb-0">Quotes</h1>
            <RouterLink :to="{ name: 'quotes.create' }" class="btn btn-primary no-print">
                <i class="bi bi-plus-lg me-2"></i>Create Quote
            </RouterLink>
        </div>

        <!-- Quote KPIs -->
        <section class="quotes-kpis" aria-label="Quote summary">
            <div v-for="k in kpis" :key="k.key" class="quotes-kpi">
                <span class="quotes-kpi__label">{{ k.label }}</span>
                <strong class="quotes-kpi__value">
                    <template v-if="k.values.length">
                        <span v-for="v in k.values" :key="v.currency" class="quotes-kpi__amount">{{ v.text }}</span>
                    </template>
                    <template v-else>—</template>
                </strong>
                <span class="quotes-kpi__hint">{{ k.hint }}</span>
            </div>
        </section>

        <!-- Status tabs -->
        <ul class="nav nav-pills flex-nowrap overflow-auto gap-1 px-3 pt-3 pb-3 no-print">
            <li class="nav-item">
                <button type="button" class="nav-link text-nowrap" :class="{ active: filters.status === '' }" @click="filters.status = ''">
                    All
                    <span class="badge rounded-pill ms-1" :class="filters.status === '' ? 'text-bg-light' : 'text-bg-secondary'">
                        {{ summary.total }}
                    </span>
                </button>
            </li>
            <li v-for="s in STATUSES" :key="s.value" class="nav-item">
                <button type="button" class="nav-link text-nowrap" :class="{ active: filters.status === s.value }" @click="filters.status = s.value">
                    {{ s.label }}
                    <span class="badge rounded-pill ms-1" :class="filters.status === s.value ? 'text-bg-light' : 'text-bg-secondary'">
                        {{ summary.by_status?.[s.value] ?? 0 }}
                    </span>
                </button>
            </li>
        </ul>

        <!-- Toolbar -->
        <div class="quotes-toolbar d-flex flex-wrap align-items-center gap-2 px-3 py-3 bg-light border-top border-bottom no-print">
            <form class="input-group quotes-search" role="search" @submit.prevent="load">
                <input v-model.trim="filters.search" type="search" class="form-control" placeholder="Search" aria-label="Search quotes" />
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

                <div v-if="filterOpen" class="card shadow filter-panel" role="dialog" aria-label="Filter quotes">
                    <div class="card-body d-flex flex-column gap-3">
                        <div>
                            <label for="quote-filter-company" class="form-label small text-muted mb-1">Company</label>
                            <select id="quote-filter-company" v-model="filters.company_id" class="form-select">
                                <option value="">All</option>
                                <option v-for="c in companies" :key="c.id" :value="String(c.id)">{{ c.name }}</option>
                            </select>
                        </div>

                        <div>
                            <label for="quote-filter-owner" class="form-label small text-muted mb-1">Owner</label>
                            <select id="quote-filter-owner" v-model="filters.owner_id" class="form-select">
                                <option value="">Anyone</option>
                                <option value="unassigned">Unassigned</option>
                                <option v-for="o in owners" :key="o.id" :value="String(o.id)">{{ o.name }}</option>
                            </select>
                        </div>

                        <div class="row g-2">
                            <div class="col-6">
                                <label for="quote-filter-from" class="form-label small text-muted mb-1">Issued from</label>
                                <input id="quote-filter-from" v-model="filters.issue_from" type="date" class="form-control" :max="filters.issue_to || null" />
                            </div>
                            <div class="col-6">
                                <label for="quote-filter-to" class="form-label small text-muted mb-1">Issued to</label>
                                <input id="quote-filter-to" v-model="filters.issue_to" type="date" class="form-control" :min="filters.issue_from || null" />
                            </div>
                        </div>

                        <div class="row g-2">
                            <div class="col-7">
                                <label for="quote-filter-sort" class="form-label small text-muted mb-1">Sort by</label>
                                <select id="quote-filter-sort" v-model="filters.sort_by" class="form-select">
                                    <option v-for="o in SORT_OPTIONS" :key="o.value" :value="o.value">{{ o.label }}</option>
                                </select>
                            </div>
                            <div class="col-5">
                                <label for="quote-filter-order" class="form-label small text-muted mb-1">Order</label>
                                <select id="quote-filter-order" v-model="filters.sort_dir" class="form-select">
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
                                :id="`quote-col-${c.key}`"
                                v-model="visibleColumns"
                                type="checkbox"
                                class="form-check-input"
                                :value="c.key"
                                :disabled="c.locked"
                            />
                            <label :for="`quote-col-${c.key}`" class="form-check-label w-100 text-nowrap">{{ c.label }}</label>
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

        <div v-else class="table-responsive quotes-scroll" :style="{ opacity: loading ? 0.6 : 1 }">
            <table class="table align-middle mb-0 quotes-table">
                <thead>
                    <tr>
                        <th v-for="c in shownColumns" :key="c.key" scope="col" :class="{ 'text-end': isRight(c.key) }">{{ c.label }}</th>
                        <th scope="col" class="no-print"><span class="visually-hidden">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="!items.length">
                        <td :colspan="shownColumns.length + 1" class="text-center text-muted py-5">No quotes found.</td>
                    </tr>

                    <tr v-for="(q, index) in items" :key="q.id">
                        <td v-for="col in shownColumns" :key="col.key" :class="{ 'text-end': isRight(col.key) }">
                            <template v-if="col.key === 'id'">{{ rowOffset + index + 1 }}</template>

                            <RouterLink v-else-if="col.key === 'number'" :to="{ name: 'quotes.show', params: { id: q.id } }" class="quote-link">
                                {{ q.quote_number }}
                            </RouterLink>

                            <template v-else-if="col.key === 'subject'">{{ q.subject }}</template>
                            <template v-else-if="col.key === 'owner'">{{ q.owner?.name ?? '—' }}</template>

                            <span v-else-if="col.key === 'company'">
                                <RouterLink v-if="q.company" :to="{ name: 'companies.show', params: { id: q.company.id } }" class="quote-link">
                                    {{ q.company.name }}
                                </RouterLink>
                                <template v-else>—</template>
                            </span>

                            <span v-else-if="col.key === 'contact'">
                                <RouterLink v-if="q.contact" :to="{ name: 'contacts.show', params: { id: q.contact.id } }" class="quote-link">
                                    {{ q.contact.name }}
                                </RouterLink>
                                <template v-else>—</template>
                            </span>

                            <span v-else-if="col.key === 'deal'">
                                <RouterLink v-if="q.deal" :to="{ name: 'deals.show', params: { id: q.deal.id } }" class="quote-link">
                                    {{ q.deal.name }}
                                </RouterLink>
                                <template v-else>—</template>
                            </span>

                            <template v-else-if="col.key === 'total'">{{ formatMoney(q.total, q.currency) }}</template>

                            <span v-else-if="col.key === 'status'">
                                <span class="badge" :class="statusBadge(q.status)">{{ statusMeta(q.status)?.label ?? q.status }}</span>
                                <span v-if="q.is_expired" class="badge text-bg-danger ms-1">Expired</span>
                            </span>

                            <span v-else-if="col.key === 'valid_until'" :class="{ 'text-danger': q.is_expired }" :title="q.is_expired ? 'Expired' : null">
                                {{ formatDateOnly(q.valid_until) }}
                            </span>

                            <template v-else-if="col.key === 'issue_date'">{{ formatDateOnly(q.issue_date) }}</template>
                            <template v-else-if="col.key === 'created_at'">{{ formatDate(q.created_at) }}</template>
                        </td>

                        <td class="text-end no-print">
                            <button
                                type="button"
                                class="btn btn-sm btn-link text-body row-menu-toggle"
                                aria-haspopup="menu"
                                :aria-expanded="menu.quote?.id === q.id"
                                :aria-label="`Actions for ${q.quote_number}`"
                                @click="toggleMenu($event, q)"
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
    <RecordDrawer :show="drawer.show" :title="drawer.quoteId ? 'Edit Quote' : 'Create Quote'" @close="requestClose">
        <QuoteFormView
            ref="formRef"
            :key="route.fullPath"
            :quote-id="drawer.quoteId"
            :prefill-deal-id="prefillDealId"
            :owners="owners"
            @saved="onQuoteSaved"
            @cancel="closeDrawer"
        />
    </RecordDrawer>

    <!-- Row actions menu -->
    <Teleport to="body">
        <ul v-if="menu.quote" class="dropdown-menu show row-menu shadow" role="menu" :style="{ top: `${menu.top}px`, right: `${menu.right}px` }">
            <li>
                <RouterLink class="dropdown-item" role="menuitem" :to="{ name: 'quotes.show', params: { id: menu.quote.id } }" @click="closeMenu">
                    <i class="bi bi-eye me-2"></i>View
                </RouterLink>
            </li>
            <li>
                <RouterLink class="dropdown-item" role="menuitem" :to="{ name: 'quotes.edit', params: { id: menu.quote.id } }" @click="closeMenu">
                    <i class="bi bi-pencil me-2"></i>Edit
                </RouterLink>
            </li>
            <li><hr class="dropdown-divider" /></li>
            <li>
                <button type="button" class="dropdown-item text-danger" role="menuitem" @click="askDelete(menu.quote)">
                    <i class="bi bi-trash me-2"></i>Delete
                </button>
            </li>
        </ul>
    </Teleport>

    <DeleteConfirmModal
        :show="!!toDelete"
        title="Delete quote"
        :message="`Delete ${toDelete?.quote_number}?`"
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
   so changing a token restyles Leads, Contacts, Companies, Deals, Products and every future module together. */
.quotes-page {
    font-family: var(--crm-font);
    font-size: var(--crm-fs);
    line-height: 1.5;
    color: var(--crm-text);
}

.quotes-title {
    font-size: 1.5rem;
    font-weight: 600;
    letter-spacing: -0.02em;
}

/* Controls: one height, one font size, one radius */
.quotes-page .form-control,
.quotes-page .form-select {
    min-height: var(--crm-control-h);
    padding-block: 0.375rem;
    font-size: var(--crm-fs);
}

.quotes-page .form-label {
    margin-bottom: 0.25rem;
    font-size: var(--crm-fs-sm);
    font-weight: 500;
    color: var(--crm-text-muted);
}

.quotes-page .btn:not(.btn-sm) {
    --bs-btn-font-size: var(--crm-fs);
    --bs-btn-font-weight: 500;
    --bs-btn-padding-x: 0.875rem;
    --bs-btn-border-radius: var(--crm-radius);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: var(--crm-control-h);
}

.quotes-page .nav-link {
    padding: 0.4rem 0.875rem;
    font-size: var(--crm-fs);
    font-weight: 500;
}

.quotes-page .badge {
    font-size: var(--crm-fs-xs);
    font-weight: 500;
}

.quotes-page .dropdown-menu,
.row-menu {
    --bs-dropdown-font-size: var(--crm-fs);
    --bs-dropdown-border-radius: var(--crm-radius);
    --bs-dropdown-item-padding-y: 0.4rem;
}

.quotes-page :deep(.pagination) {
    --bs-pagination-font-size: var(--crm-fs);
}

/* Quote KPIs */
.quotes-kpis {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(12rem, 1fr));
    border-bottom: 1px solid var(--crm-border);
}

.quotes-kpi {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    padding: 1rem 1.25rem;
}

.quotes-kpi + .quotes-kpi {
    border-left: 1px solid var(--crm-border);
}

.quotes-kpi__label {
    font-size: var(--crm-fs-sm);
    font-weight: 500;
    color: var(--crm-text-muted);
}

.quotes-kpi__value {
    display: flex;
    flex-wrap: wrap;
    gap: 0 0.75rem;
    font-size: 1.125rem;
    font-weight: 600;
    font-variant-numeric: tabular-nums;
    line-height: 1.4;
}

.quotes-kpi__hint {
    font-size: var(--crm-fs-xs);
    color: var(--crm-text-muted);
}

/* Toolbar */
.quotes-search {
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
.quotes-table {
    --bs-table-bg: transparent;
    font-size: var(--crm-fs);
    color: var(--crm-text);
    font-variant-numeric: tabular-nums;
}

.quotes-table thead th {
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

.quotes-table tbody td {
    padding: 0.75rem 1rem;
    white-space: nowrap;
    border-bottom: 1px solid var(--crm-border);
}

.quotes-table tbody tr:last-child td {
    border-bottom: 0;
}

.quote-link {
    color: inherit;
    text-decoration: none;
}

.quote-link:hover {
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
    .quotes-kpi + .quotes-kpi {
        border-top: 1px solid var(--crm-border);
        border-left: 0;
    }
}

@media print {
    .no-print {
        display: none !important;
    }

    .quotes-page {
        border: 0 !important;
        box-shadow: none !important;
    }

    .quotes-page .table-responsive {
        overflow: visible !important;
        max-height: none !important;
    }
}
</style>
