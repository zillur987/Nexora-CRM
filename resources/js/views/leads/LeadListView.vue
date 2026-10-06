<script setup>
import { computed, onBeforeUnmount, onMounted, ref, unref, watch } from 'vue';
import { leadsApi } from '@/api/leads';
import { usersApi } from '@/api/users';
import { LEAD_SOURCES, LEAD_STATUSES, leadSourceMeta } from '@/constants';
import { useListQuery } from '@/composables/useListQuery';
import { useToastStore } from '@/stores/toast';
import { downloadCsv } from '@/utils/csv';
import { parseApiError } from '@/utils/errors';
import { formatDate, money } from '@/utils/format';
import ConfirmModal from '@/components/ConfirmModal.vue';
import LoadingBlock from '@/components/LoadingBlock.vue';
import Pagination from '@/components/Pagination.vue';
import ScoreBar from '@/components/ScoreBar.vue';

const toast = useToastStore();

/* -------------------------------------------------------------------------- */
/* Static config                                                              */
/* -------------------------------------------------------------------------- */

const DEFAULT_FILTERS = {
    search: '',
    status: '',
    source: '',
    assigned_to: '',
    sort_by: 'created_at',
    sort_dir: 'desc',
};

const SORT_OPTIONS = [
    { value: 'created_at', label: 'Created' },
    { value: 'score', label: 'Score' },
    { value: 'estimated_value', label: 'Value' },
    { value: 'last_contacted_at', label: 'Last contacted' },
    { value: 'last_name', label: 'Last name' },
    { value: 'company', label: 'Company' },
];

const convertStatus = (lead) => (lead.is_converted ? 'Converted' : 'Not Converted');

// Order here = order of table columns, the Columns dropdown and the CSV.
// Cell markup lives in the template; `csv` is the plain-text value for export.
const COLUMNS = [
    { key: 'id', label: 'Id', default: true, csv: (l, index) => rowOffset.value + index + 1 },
    { key: 'name', label: 'Name', locked: true, default: true, csv: (l) => l.full_name },
    { key: 'email', label: 'Email', default: true, csv: (l) => l.email },
    { key: 'phone', label: 'Phone number', default: true, csv: (l) => l.phone },
    { key: 'owner', label: 'Lead Owner', default: true, csv: (l) => l.owner?.name },
    { key: 'status', label: 'Lead Status', default: true, csv: (l) => l.status },
    { key: 'value', label: 'Lead Value', default: true, csv: (l) => l.estimated_value },
    { key: 'source', label: 'Lead Source', default: true, csv: (l) => leadSourceMeta[l.source]?.label ?? l.source },
    { key: 'convert', label: 'Convert Status', default: true, csv: convertStatus },
    { key: 'company', label: 'Company', default: false, csv: (l) => l.company },
    { key: 'score', label: 'Score', default: false, csv: (l) => l.score },
    { key: 'created_at', label: 'Created', default: false, csv: (l) => l.created_at },
    { key: 'last_contacted_at', label: 'Last contacted', default: false, csv: (l) => l.last_contacted_at },
];

/* -------------------------------------------------------------------------- */
/* Data                                                                       */
/* -------------------------------------------------------------------------- */

const { filters, items, meta, loading, error, load, goToPage, reset } = useListQuery(leadsApi.list, {
    ...DEFAULT_FILTERS,
});

const summary = ref({});
const owners = ref([]);

async function loadSummary() {
    try {
        summary.value = await leadsApi.summary();
    } catch {
        /* tabs just show no counts; the list itself reports its own errors */
    }
}

// Sequential row number shown in the "Id" column (1, 2, 3 ...), continuing across pages.
// It is derived from the row's position, so it re-balances itself after every reload.
const rowOffset = computed(() => {
    const m = unref(meta);
    if (!m) return 0;
    if (Number.isInteger(m.from)) return m.from - 1;
    return ((m.current_page ?? 1) - 1) * (m.per_page ?? 0);
});

/* -------------------------------------------------------------------------- */
/* Filter panel                                                               */
/* -------------------------------------------------------------------------- */

const filterOpen = ref(false);
const filterRef = ref(null);

// Counts only what lives inside the panel (search and status have their own controls).
const activeFilterCount = computed(() => {
    const f = unref(filters);
    const fields = ['source', 'assigned_to'].filter((key) => f[key] !== '').length;
    const sorted = f.sort_by !== DEFAULT_FILTERS.sort_by || f.sort_dir !== DEFAULT_FILTERS.sort_dir;
    return fields + (sorted ? 1 : 0);
});

/* -------------------------------------------------------------------------- */
/* Column visibility (persisted per browser)                                  */
/* -------------------------------------------------------------------------- */

const COLUMNS_STORAGE_KEY = 'leads.visibleColumns.v2';
const columnsOpen = ref(false);
const columnsRef = ref(null);

function loadVisibleColumns() {
    const known = COLUMNS.map((c) => c.key);
    try {
        const saved = JSON.parse(localStorage.getItem(COLUMNS_STORAGE_KEY));
        if (Array.isArray(saved)) {
            return [...new Set(['name', ...saved.filter((key) => known.includes(key))])];
        }
    } catch {
        /* fall through to defaults */
    }
    return COLUMNS.filter((c) => c.default).map((c) => c.key);
}

const visibleColumns = ref(loadVisibleColumns());

watch(
    visibleColumns,
    (value) => {
        try {
            localStorage.setItem(COLUMNS_STORAGE_KEY, JSON.stringify(value));
        } catch {
            /* storage unavailable: keep working without persistence */
        }
    },
    { deep: true },
);

const shownColumns = computed(() => COLUMNS.filter((c) => visibleColumns.value.includes(c.key)));
const isVisible = (key) => visibleColumns.value.includes(key);

/* -------------------------------------------------------------------------- */
/* Row actions menu (teleported so the scrolling table can't clip it)         */
/* -------------------------------------------------------------------------- */

const menu = ref({ lead: null, top: 0, right: 0 });

function closeMenu() {
    menu.value = { lead: null, top: 0, right: 0 };
}

function toggleMenu(event, lead) {
    if (menu.value.lead?.id === lead.id) return closeMenu();

    const rect = event.currentTarget.getBoundingClientRect();
    menu.value = {
        lead,
        top: rect.bottom + 4,
        right: window.innerWidth - rect.right,
    };
}

/* -------------------------------------------------------------------------- */
/* Delete                                                                     */
/* -------------------------------------------------------------------------- */

const toDelete = ref(null);
const deleting = ref(false);

function askDelete(lead) {
    closeMenu();
    toDelete.value = lead;
}

async function confirmDelete() {
    deleting.value = true;
    try {
        const wasLastRowOnPage = items.value.length === 1;
        const page = unref(meta)?.current_page ?? 1;

        await leadsApi.remove(toDelete.value.id);
        toast.success('Lead deleted.');
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

// Exports the rows currently loaded (current page, current filters, visible columns).
function exportCsv() {
    const columns = shownColumns.value;
    downloadCsv(
        `leads-${new Date().toISOString().slice(0, 10)}.csv`,
        columns.map((c) => c.label),
        items.value.map((lead, index) => columns.map((c) => c.csv(lead, index))),
    );
}

/* -------------------------------------------------------------------------- */
/* Global listeners                                                           */
/* -------------------------------------------------------------------------- */

function onDocumentClick(event) {
    const target = event.target;
    if (filterOpen.value && !filterRef.value?.contains(target)) filterOpen.value = false;
    if (columnsOpen.value && !columnsRef.value?.contains(target)) columnsOpen.value = false;
    if (menu.value.lead && !target.closest?.('.row-menu, .row-menu-toggle')) closeMenu();
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
    try {
        owners.value = await usersApi.options();
    } catch {
        /* owner filter stays empty */
    }
});

onBeforeUnmount(() => {
    document.removeEventListener('click', onDocumentClick);
    document.removeEventListener('keydown', onKeydown);
    window.removeEventListener('resize', closeMenu);
    window.removeEventListener('scroll', closeMenu, true);
});
</script>

<template>
    <div class="card leads-page rounded-3 overflow-visible">
        <!-- Header -->
        <div class="card-header bg-white d-flex justify-content-between align-items-center px-3 py-3 rounded-top-3">
            <h1 class="leads-title mb-0">Leads</h1>
            <RouterLink :to="{ name: 'leads.create' }" class="btn btn-primary no-print">
                <i class="bi bi-plus-lg me-2"></i>Create Lead
            </RouterLink>
        </div>

        <!-- Status tabs -->
        <ul class="nav nav-pills flex-nowrap overflow-auto gap-1 px-3 pt-3 pb-3 no-print">
            <li class="nav-item">
                <button type="button" class="nav-link text-nowrap" :class="{ active: filters.status === '' }" @click="filters.status = ''">
                    All
                </button>
            </li>
            <li v-for="s in LEAD_STATUSES" :key="s.value" class="nav-item">
                <button
                    type="button"
                    class="nav-link text-nowrap"
                    :class="{ active: filters.status === s.value }"
                    @click="filters.status = s.value"
                >
                    {{ s.label }}
                    <span
                        v-if="summary[s.value] !== undefined"
                        class="badge rounded-pill ms-1"
                        :class="filters.status === s.value ? 'text-bg-light' : 'text-bg-secondary'"
                    >
                        {{ summary[s.value] }}
                    </span>
                </button>
            </li>
        </ul>

        <!-- Toolbar -->
        <div class="leads-toolbar d-flex flex-wrap align-items-center gap-2 px-3 py-3 bg-light border-top border-bottom no-print">
            <form class="input-group leads-search" role="search" @submit.prevent="load">
                <input
                    v-model.trim="filters.search"
                    type="search"
                    class="form-control"
                    placeholder="Search"
                    aria-label="Search leads"
                />
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

                <div v-if="filterOpen" class="card shadow filter-panel" role="dialog" aria-label="Filter leads">
                    <div class="card-body d-flex flex-column gap-3">
                        <div>
                            <label for="lead-filter-source" class="form-label small text-muted mb-1">Source</label>
                            <select id="lead-filter-source" v-model="filters.source" class="form-select">
                                <option value="">All</option>
                                <option v-for="s in LEAD_SOURCES" :key="s.value" :value="s.value">{{ s.label }}</option>
                            </select>
                        </div>

                        <div>
                            <label for="lead-filter-owner" class="form-label small text-muted mb-1">Owner</label>
                            <select id="lead-filter-owner" v-model="filters.assigned_to" class="form-select">
                                <option value="">Anyone</option>
                                <option value="unassigned">Unassigned</option>
                                <option v-for="o in owners" :key="o.id" :value="String(o.id)">{{ o.name }}</option>
                            </select>
                        </div>

                        <div class="row g-2">
                            <div class="col-7">
                                <label for="lead-filter-sort" class="form-label small text-muted mb-1">Sort by</label>
                                <select id="lead-filter-sort" v-model="filters.sort_by" class="form-select">
                                    <option v-for="o in SORT_OPTIONS" :key="o.value" :value="o.value">{{ o.label }}</option>
                                </select>
                            </div>
                            <div class="col-5">
                                <label for="lead-filter-order" class="form-label small text-muted mb-1">Order</label>
                                <select id="lead-filter-order" v-model="filters.sort_dir" class="form-select">
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
                                :id="`lead-col-${c.key}`"
                                v-model="visibleColumns"
                                type="checkbox"
                                class="form-check-input"
                                :value="c.key"
                                :disabled="c.locked"
                            />
                            <label :for="`lead-col-${c.key}`" class="form-check-label w-100 text-nowrap">{{ c.label }}</label>
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

        <div v-else class="table-responsive leads-scroll" :style="{ opacity: loading ? 0.6 : 1 }">
            <table class="table align-middle mb-0 leads-table">
                <thead>
                    <tr>
                        <th v-for="c in shownColumns" :key="c.key" scope="col">{{ c.label }}</th>
                        <th scope="col" class="no-print"><span class="visually-hidden">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="!items.length">
                        <td :colspan="shownColumns.length + 1" class="text-center text-muted py-5">No leads found.</td>
                    </tr>

                    <!-- Cell order must match COLUMNS -->
                    <tr v-for="(l, index) in items" :key="l.id">
                        <td v-if="isVisible('id')">{{ rowOffset + index + 1 }}</td>
                        <td>
                            <RouterLink :to="{ name: 'leads.show', params: { id: l.id } }" class="lead-link">
                                {{ l.full_name }}
                            </RouterLink>
                        </td>
                        <td v-if="isVisible('email')">{{ l.email ?? '—' }}</td>
                        <td v-if="isVisible('phone')">{{ l.phone ?? '—' }}</td>
                        <td v-if="isVisible('owner')">{{ l.owner?.name ?? '—' }}</td>
                        <td v-if="isVisible('status')">{{ l.status }}</td>
                        <td v-if="isVisible('value')">{{ l.estimated_value ? money(l.estimated_value, l.currency) : '—' }}</td>
                        <td v-if="isVisible('source')">{{ leadSourceMeta[l.source]?.label ?? l.source }}</td>
                        <td v-if="isVisible('convert')">{{ convertStatus(l) }}</td>
                        <td v-if="isVisible('company')">{{ l.company ?? '—' }}</td>
                        <td v-if="isVisible('score')"><ScoreBar :value="l.score" /></td>
                        <td v-if="isVisible('created_at')">{{ formatDate(l.created_at) }}</td>
                        <td v-if="isVisible('last_contacted_at')">{{ l.last_contacted_at ? formatDate(l.last_contacted_at) : '—' }}</td>
                        <td class="text-end no-print">
                            <button
                                type="button"
                                class="btn btn-sm btn-link text-body row-menu-toggle"
                                aria-haspopup="menu"
                                :aria-expanded="menu.lead?.id === l.id"
                                :aria-label="`Actions for ${l.full_name}`"
                                @click="toggleMenu($event, l)"
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

    <!-- Row actions menu -->
    <Teleport to="body">
        <ul
            v-if="menu.lead"
            class="dropdown-menu show row-menu shadow"
            role="menu"
            :style="{ top: `${menu.top}px`, right: `${menu.right}px` }"
        >
            <li>
                <RouterLink class="dropdown-item" role="menuitem" :to="{ name: 'leads.show', params: { id: menu.lead.id } }">
                    <i class="bi bi-eye me-2"></i>View
                </RouterLink>
            </li>
            <template v-if="!menu.lead.is_converted">
                <li>
                    <RouterLink class="dropdown-item" role="menuitem" :to="{ name: 'leads.edit', params: { id: menu.lead.id } }">
                        <i class="bi bi-pencil me-2"></i>Edit
                    </RouterLink>
                </li>
                <li><hr class="dropdown-divider" /></li>
                <li>
                    <button type="button" class="dropdown-item text-danger" role="menuitem" @click="askDelete(menu.lead)">
                        <i class="bi bi-trash me-2"></i>Delete
                    </button>
                </li>
            </template>
        </ul>
    </Teleport>

    <ConfirmModal
        :show="!!toDelete"
        title="Delete lead"
        :message="`Delete ${toDelete?.full_name}?`"
        :loading="deleting"
        @confirm="confirmDelete"
        @cancel="toDelete = null"
    />
</template>

<!-- Inter. Move this import to index.html or your global CSS if you want the font app-wide. -->
<style>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&display=swap');
</style>

<style scoped>
/* Design tokens (the teleported row menu sits outside .leads-page, so it is listed too) */
.leads-page,
.row-menu {
    --lp-font: 'Inter', system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
    --lp-fs: 0.875rem; /* 14px: body, controls, table cells */
    --lp-fs-sm: 0.8125rem; /* 13px: labels, table headers */
    --lp-control-h: 2.25rem; /* 36px: every input, select and button */
    --lp-radius: 0.5rem;
    --lp-text: #101828;
    --lp-text-muted: #667085;
    --lp-border: #e4e7ec;

    --bs-border-radius: var(--lp-radius);
    --bs-body-font-size: var(--lp-fs);

    font-family: var(--lp-font);
    font-size: var(--lp-fs);
    line-height: 1.5;
    color: var(--lp-text);
    -webkit-font-smoothing: antialiased;
}

.leads-title {
    font-size: 1.5rem;
    font-weight: 600;
    letter-spacing: -0.02em;
}

/* Controls: one height, one font size, one radius */
.leads-page .form-control,
.leads-page .form-select {
    min-height: var(--lp-control-h);
    padding-block: 0.375rem;
    font-size: var(--lp-fs);
}

.leads-page .form-label {
    margin-bottom: 0.25rem;
    font-size: var(--lp-fs-sm);
    font-weight: 500;
    color: var(--lp-text-muted);
}

.leads-page .btn:not(.btn-sm) {
    --bs-btn-font-size: var(--lp-fs);
    --bs-btn-font-weight: 500;
    --bs-btn-padding-x: 0.875rem;
    --bs-btn-border-radius: var(--lp-radius);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: var(--lp-control-h);
}

.leads-page .nav-link {
    padding: 0.4rem 0.875rem;
    font-size: var(--lp-fs);
    font-weight: 500;
}

.leads-page .badge {
    font-size: 0.75rem;
    font-weight: 500;
}

.leads-page .dropdown-menu,
.row-menu {
    --bs-dropdown-font-size: var(--lp-fs);
    --bs-dropdown-border-radius: var(--lp-radius);
    --bs-dropdown-item-padding-y: 0.4rem;
}

.leads-page :deep(.pagination) {
    --bs-pagination-font-size: var(--lp-fs);
}

/* Toolbar */
.leads-search {
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
.leads-table {
    --bs-table-bg: transparent;
    font-size: var(--lp-fs);
    color: var(--lp-text);
    font-variant-numeric: tabular-nums;
}

.leads-table thead th {
    position: sticky;
    top: 0;
    z-index: 1;
    padding: 0.75rem 1rem;
    font-size: var(--lp-fs-sm);
    font-weight: 600;
    color: #344054;
    white-space: nowrap;
    background: #fff;
    border-bottom: 0;
    /* A shadow instead of a border, so the divider stays visible on the sticky header. */
    box-shadow: inset 0 -1px 0 var(--lp-border);
}

.leads-table tbody td {
    padding: 0.75rem 1rem;
    white-space: nowrap;
    border-bottom: 1px solid var(--lp-border);
}

.leads-table tbody tr:last-child td {
    border-bottom: 0;
}

.lead-link {
    color: inherit;
    text-decoration: none;
}

.lead-link:hover {
    color: var(--bs-primary);
    text-decoration: underline;
}

/* The table list is its own scroll area (horizontal + vertical), so the page itself doesn't scroll. */
.leads-scroll {
    overflow: auto;
    max-height: max(20rem, calc(100vh - 24rem));
    scrollbar-width: thin;
    scrollbar-color: #adb5bd transparent;
}

.leads-scroll::-webkit-scrollbar {
    width: 6px;
    height: 6px;
}

.leads-scroll::-webkit-scrollbar-track {
    background: transparent;
}

.leads-scroll::-webkit-scrollbar-thumb {
    background: #adb5bd;
    border-radius: 999px;
}

.leads-scroll::-webkit-scrollbar-thumb:hover {
    background: #868e96;
}

/* Row actions menu */
.row-menu {
    position: fixed;
    left: auto;
    z-index: 1060;
    min-width: 9rem;
    margin: 0;
}

@media print {
    .no-print {
        display: none !important;
    }

    .leads-page {
        border: 0 !important;
        box-shadow: none !important;
    }

    .leads-page .table-responsive {
        overflow: visible !important;
        max-height: none !important;
    }
}
</style>