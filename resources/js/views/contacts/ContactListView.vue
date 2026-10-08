<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref, unref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { companiesApi, companyLookupsApi } from '@/api/companies';
import { contactLookupsApi, contactsApi } from '@/api/contacts';
import { usersApi } from '@/api/users';
import { useColumnVisibility } from '@/composables/useColumnVisibility';
import { useListQuery } from '@/composables/useListQuery';
import { useToastStore } from '@/stores/toast';
import { CONTACT_CSV_COLUMNS, contactToCsvRow } from '@/utils/contactCsv';
import { downloadCsv } from '@/utils/csv';
import { formatDate } from '@/utils/format';
import { formatDateOnly } from '@/utils/dateOnly';
import { parseApiError } from '@/utils/errors';
import ConfirmModal from '@/components/ConfirmModal.vue';
import DeleteConfirmModal from '@/components/DeleteConfirmModal.vue';
import LoadingBlock from '@/components/LoadingBlock.vue';
import Pagination from '@/components/Pagination.vue';
import RecordDrawer from '@/components/RecordDrawer.vue';
import ContactFormView from '@/views/contacts/ContactFormView.vue';

const toast = useToastStore();
const route = useRoute();
const router = useRouter();

/* -------------------------------------------------------------------------- */
/* Static config                                                              */
/* -------------------------------------------------------------------------- */

const DEFAULT_FILTERS = {
    search: '',
    contact_stage_id: '',
    contact_source_id: '',
    industry_id: '',
    company_id: '',
    owner_id: '',
    country: '',
    sort_by: 'created_at',
    sort_dir: 'desc',
};

const SORT_OPTIONS = [
    { value: 'created_at', label: 'Created' },
    { value: 'first_name', label: 'First name' },
    { value: 'last_name', label: 'Last name' },
    { value: 'birthday', label: 'Birthday' },
    { value: 'present_country', label: 'Country' },
];

// Order here = order of table columns and of the Columns dropdown.
const COLUMNS = [
    { key: 'id', label: 'Id', default: true },
    { key: 'name', label: 'Contact name', locked: true, default: true },
    { key: 'owner', label: 'Contact owner', default: true },
    { key: 'company', label: 'Company', default: true },
    { key: 'job_title', label: 'Job title', default: true },
    { key: 'email', label: 'Email', default: true },
    { key: 'phone', label: 'Phone number', default: true },
    { key: 'stage', label: 'Contact stage', default: true },
    { key: 'department', label: 'Department', default: false },
    { key: 'industry', label: 'Industry', default: false },
    { key: 'source', label: 'Contact source', default: false },
    { key: 'birthday', label: 'Birthday', default: false },
    { key: 'present', label: 'Present address', default: false },
    { key: 'country', label: 'Country', default: false },
    { key: 'created_at', label: 'Created', default: false },
];

const { visible: visibleColumns, shown: shownColumns } = useColumnVisibility('contacts.visibleColumns.v1', COLUMNS);

/* -------------------------------------------------------------------------- */
/* Data                                                                       */
/* -------------------------------------------------------------------------- */

const { filters, items, meta, loading, error, load, goToPage, reset } = useListQuery(contactsApi.list, {
    ...DEFAULT_FILTERS,
});

const summary = ref({ total: 0, by_stage: {} });
const owners = ref([]);
const industries = ref([]);
const sources = ref([]);
const stages = ref([]);
const companies = ref([]);

async function loadSummary() {
    try {
        summary.value = await contactsApi.summary();
    } catch {
        /* tabs just show no counts; the list itself reports its own errors */
    }
}

const refresh = () => Promise.all([load(), loadSummary()]); // reload rows + tab counts

// Sequential row number shown in the "Id" column (1, 2, 3 ...), continuing across pages.
const rowOffset = computed(() => {
    const m = unref(meta);
    if (!m) return 0;
    if (Number.isInteger(m.from)) return m.from - 1;
    return ((m.current_page ?? 1) - 1) * (m.per_page ?? 0);
});

const sortByName = (a, b) => a.name.localeCompare(b.name);

/** The form creates industries / sources / stages on the fly; keep the filter + tabs in sync. */
function onLookupCreated({ kind, item }) {
    const target = { industries, 'contact-sources': sources, 'contact-stages': stages }[kind];
    if (target && !target.value.some((existing) => existing.id === item.id)) {
        target.value = [...target.value, item].sort(sortByName);
    }
}

async function loadCompanies() {
    try {
        companies.value = await companiesApi.options();
    } catch {
        /* the company filter just stays empty */
    }
}

/* -------------------------------------------------------------------------- */
/* Filter panel + columns dropdown                                            */
/* -------------------------------------------------------------------------- */

const filterOpen = ref(false);
const filterRef = ref(null);
const columnsOpen = ref(false);
const columnsRef = ref(null);

// Counts only what lives inside the panel (search and the stage tabs have their own controls).
const activeFilterCount = computed(() => {
    const f = unref(filters);
    const fields = ['industry_id', 'contact_source_id', 'company_id', 'owner_id', 'country'].filter((key) => f[key] !== '').length;
    const sorted = f.sort_by !== DEFAULT_FILTERS.sort_by || f.sort_dir !== DEFAULT_FILTERS.sort_dir;
    return fields + (sorted ? 1 : 0);
});

/* -------------------------------------------------------------------------- */
/* Row actions menu (teleported so the scrolling table can't clip it)         */
/* -------------------------------------------------------------------------- */

const menu = ref({ contact: null, top: 0, right: 0 });

function closeMenu() {
    menu.value = { contact: null, top: 0, right: 0 };
}

function toggleMenu(event, contact) {
    if (menu.value.contact?.id === contact.id) return closeMenu();

    const rect = event.currentTarget.getBoundingClientRect();
    menu.value = { contact, top: rect.bottom + 4, right: window.innerWidth - rect.right };
}

/* -------------------------------------------------------------------------- */
/* Delete                                                                     */
/* -------------------------------------------------------------------------- */

const toDelete = ref(null);
const deleting = ref(false);

function askDelete(contact) {
    closeMenu();
    toDelete.value = contact;
}

async function confirmDelete() {
    deleting.value = true;
    try {
        const wasLastRowOnPage = items.value.length === 1;
        const page = unref(meta)?.current_page ?? 1;

        await contactsApi.remove(toDelete.value.id);
        toast.success('Contact deleted.');
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
        `contacts-${new Date().toISOString().slice(0, 10)}.csv`,
        CONTACT_CSV_COLUMNS,
        items.value.map(contactToCsvRow),
    );
}

/* -------------------------------------------------------------------------- */
/* Create / edit drawer (driven by the route, so it is linkable and survives refresh) */
/* -------------------------------------------------------------------------- */

const drawer = reactive({ show: false, contactId: null });
const formRef = ref(null);
const confirmDiscard = ref(false);

watch(
    () => [route.name, route.params.id],
    ([name, id]) => {
        drawer.show = name === 'contacts.create' || name === 'contacts.edit';
        if (drawer.show) drawer.contactId = name === 'contacts.edit' ? String(id) : null;
    },
    { immediate: true },
);

function closeDrawer() {
    confirmDiscard.value = false;
    router.replace({ name: 'contacts.index', query: route.query });
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
async function onContactSaved(saved, { another } = {}) {
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
    if (menu.value.contact && !target.closest?.('.row-menu, .row-menu-toggle')) closeMenu();
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
    const [ownerList, industryList, sourceList, stageList] = await Promise.allSettled([
        usersApi.options(),
        companyLookupsApi.list('industries'),
        contactLookupsApi.list('contact-sources'),
        contactLookupsApi.list('contact-stages'),
        loadCompanies(),
    ]);
    if (ownerList.status === 'fulfilled') owners.value = ownerList.value;
    if (industryList.status === 'fulfilled') industries.value = industryList.value;
    if (sourceList.status === 'fulfilled') sources.value = sourceList.value;
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
    <div class="card contacts-page rounded-3 overflow-visible">
        <!-- Header -->
        <div class="card-header bg-white d-flex justify-content-between align-items-center px-3 py-3 rounded-top-3">
            <h1 class="contacts-title mb-0">Contacts</h1>
            <RouterLink :to="{ name: 'contacts.create' }" class="btn btn-primary no-print">
                <i class="bi bi-plus-lg me-2"></i>Create Contact
            </RouterLink>
        </div>

        <!-- Stage tabs -->
        <ul class="nav nav-pills flex-nowrap overflow-auto gap-1 px-3 pt-3 pb-3 no-print">
            <li class="nav-item">
                <button type="button" class="nav-link text-nowrap" :class="{ active: filters.contact_stage_id === '' }" @click="filters.contact_stage_id = ''">
                    All
                    <span class="badge rounded-pill ms-1" :class="filters.contact_stage_id === '' ? 'text-bg-light' : 'text-bg-secondary'">
                        {{ summary.total }}
                    </span>
                </button>
            </li>
            <li v-for="s in stages" :key="s.id" class="nav-item">
                <button
                    type="button"
                    class="nav-link text-nowrap"
                    :class="{ active: filters.contact_stage_id === String(s.id) }"
                    @click="filters.contact_stage_id = String(s.id)"
                >
                    {{ s.name }}
                    <span
                        class="badge rounded-pill ms-1"
                        :class="filters.contact_stage_id === String(s.id) ? 'text-bg-light' : 'text-bg-secondary'"
                    >
                        {{ summary.by_stage[s.id] ?? 0 }}
                    </span>
                </button>
            </li>
        </ul>

        <!-- Toolbar -->
        <div class="contacts-toolbar d-flex flex-wrap align-items-center gap-2 px-3 py-3 bg-light border-top border-bottom no-print">
            <form class="input-group contacts-search" role="search" @submit.prevent="load">
                <input v-model.trim="filters.search" type="search" class="form-control" placeholder="Search" aria-label="Search contacts" />
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

                <div v-if="filterOpen" class="card shadow filter-panel" role="dialog" aria-label="Filter contacts">
                    <div class="card-body d-flex flex-column gap-3">
                        <div>
                            <label for="contact-filter-company" class="form-label small text-muted mb-1">Company</label>
                            <select id="contact-filter-company" v-model="filters.company_id" class="form-select">
                                <option value="">All</option>
                                <option v-for="c in companies" :key="c.id" :value="String(c.id)">{{ c.name }}</option>
                            </select>
                        </div>

                        <div>
                            <label for="contact-filter-industry" class="form-label small text-muted mb-1">Industry</label>
                            <select id="contact-filter-industry" v-model="filters.industry_id" class="form-select">
                                <option value="">All</option>
                                <option v-for="i in industries" :key="i.id" :value="String(i.id)">{{ i.name }}</option>
                            </select>
                        </div>

                        <div>
                            <label for="contact-filter-source" class="form-label small text-muted mb-1">Contact source</label>
                            <select id="contact-filter-source" v-model="filters.contact_source_id" class="form-select">
                                <option value="">All</option>
                                <option v-for="s in sources" :key="s.id" :value="String(s.id)">{{ s.name }}</option>
                            </select>
                        </div>

                        <div>
                            <label for="contact-filter-owner" class="form-label small text-muted mb-1">Owner</label>
                            <select id="contact-filter-owner" v-model="filters.owner_id" class="form-select">
                                <option value="">Anyone</option>
                                <option value="unassigned">Unassigned</option>
                                <option v-for="o in owners" :key="o.id" :value="String(o.id)">{{ o.name }}</option>
                            </select>
                        </div>

                        <div>
                            <label for="contact-filter-country" class="form-label small text-muted mb-1">Present country</label>
                            <input id="contact-filter-country" v-model.trim="filters.country" type="text" class="form-control" placeholder="e.g. Bangladesh" />
                        </div>

                        <div class="row g-2">
                            <div class="col-7">
                                <label for="contact-filter-sort" class="form-label small text-muted mb-1">Sort by</label>
                                <select id="contact-filter-sort" v-model="filters.sort_by" class="form-select">
                                    <option v-for="o in SORT_OPTIONS" :key="o.value" :value="o.value">{{ o.label }}</option>
                                </select>
                            </div>
                            <div class="col-5">
                                <label for="contact-filter-order" class="form-label small text-muted mb-1">Order</label>
                                <select id="contact-filter-order" v-model="filters.sort_dir" class="form-select">
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
                                :id="`contact-col-${c.key}`"
                                v-model="visibleColumns"
                                type="checkbox"
                                class="form-check-input"
                                :value="c.key"
                                :disabled="c.locked"
                            />
                            <label :for="`contact-col-${c.key}`" class="form-check-label w-100 text-nowrap">{{ c.label }}</label>
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

        <div v-else class="table-responsive contacts-scroll" :style="{ opacity: loading ? 0.6 : 1 }">
            <table class="table align-middle mb-0 contacts-table">
                <thead>
                    <tr>
                        <th v-for="c in shownColumns" :key="c.key" scope="col">{{ c.label }}</th>
                        <th scope="col" class="no-print"><span class="visually-hidden">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="!items.length">
                        <td :colspan="shownColumns.length + 1" class="text-center text-muted py-5">No contacts found.</td>
                    </tr>

                    <tr v-for="(c, index) in items" :key="c.id">
                        <td v-for="col in shownColumns" :key="col.key">
                            <template v-if="col.key === 'id'">{{ rowOffset + index + 1 }}</template>

                            <RouterLink v-else-if="col.key === 'name'" :to="{ name: 'contacts.show', params: { id: c.id } }" class="contact-link">
                                {{ c.full_name }}
                            </RouterLink>

                            <template v-else-if="col.key === 'owner'">{{ c.owner?.name ?? '—' }}</template>

                            <span v-else-if="col.key === 'company'">
                                <RouterLink v-if="c.company" :to="{ name: 'companies.show', params: { id: c.company.id } }" class="contact-link">
                                    {{ c.company.name }}
                                </RouterLink>
                                <template v-else>—</template>
                            </span>

                            <template v-else-if="col.key === 'job_title'">{{ c.job_title ?? '—' }}</template>

                            <span v-else-if="col.key === 'email'">
                                <a v-if="c.email" :href="`mailto:${c.email}`" class="contact-link">{{ c.email }}</a>
                                <template v-else>—</template>
                            </span>

                            <template v-else-if="col.key === 'phone'">{{ c.phone ?? '—' }}</template>

                            <span v-else-if="col.key === 'stage'">
                                <span v-if="c.stage" class="badge text-bg-primary">{{ c.stage.name }}</span>
                                <template v-else>—</template>
                            </span>

                            <template v-else-if="col.key === 'department'">{{ c.department ?? '—' }}</template>
                            <template v-else-if="col.key === 'industry'">{{ c.industry?.name ?? '—' }}</template>
                            <template v-else-if="col.key === 'source'">{{ c.source?.name ?? '—' }}</template>
                            <template v-else-if="col.key === 'birthday'">{{ formatDateOnly(c.birthday) }}</template>
                            <template v-else-if="col.key === 'present'">{{ c.present_full_address ?? '—' }}</template>
                            <template v-else-if="col.key === 'country'">{{ c.present_country ?? '—' }}</template>
                            <template v-else-if="col.key === 'created_at'">{{ formatDate(c.created_at) }}</template>
                        </td>

                        <td class="text-end no-print">
                            <button
                                type="button"
                                class="btn btn-sm btn-link text-body row-menu-toggle"
                                aria-haspopup="menu"
                                :aria-expanded="menu.contact?.id === c.id"
                                :aria-label="`Actions for ${c.full_name}`"
                                @click="toggleMenu($event, c)"
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
    <RecordDrawer :show="drawer.show" :title="drawer.contactId ? 'Edit Contact' : 'Create Contact'" @close="requestClose">
        <ContactFormView
            ref="formRef"
            :key="route.fullPath"
            :contact-id="drawer.contactId"
            :owners="owners"
            :industries="industries"
            :sources="sources"
            :stages="stages"
            @saved="onContactSaved"
            @lookup-created="onLookupCreated"
            @cancel="closeDrawer"
        />
    </RecordDrawer>

    <!-- Row actions menu -->
    <Teleport to="body">
        <ul v-if="menu.contact" class="dropdown-menu show row-menu shadow" role="menu" :style="{ top: `${menu.top}px`, right: `${menu.right}px` }">
            <li>
                <RouterLink class="dropdown-item" role="menuitem" :to="{ name: 'contacts.show', params: { id: menu.contact.id } }" @click="closeMenu">
                    <i class="bi bi-eye me-2"></i>View
                </RouterLink>
            </li>
            <li>
                <RouterLink class="dropdown-item" role="menuitem" :to="{ name: 'contacts.edit', params: { id: menu.contact.id } }" @click="closeMenu">
                    <i class="bi bi-pencil me-2"></i>Edit
                </RouterLink>
            </li>
            <li><hr class="dropdown-divider" /></li>
            <li>
                <button type="button" class="dropdown-item text-danger" role="menuitem" @click="askDelete(menu.contact)">
                    <i class="bi bi-trash me-2"></i>Delete
                </button>
            </li>
        </ul>
    </Teleport>

    <DeleteConfirmModal
        :show="!!toDelete"
        title="Delete contact"
        :message="`Delete ${toDelete?.full_name}?`"
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
   so changing a token restyles Leads, Companies and every future module together. */
.contacts-page {
    font-family: var(--crm-font);
    font-size: var(--crm-fs);
    line-height: 1.5;
    color: var(--crm-text);
}

.contacts-title {
    font-size: 1.5rem;
    font-weight: 600;
    letter-spacing: -0.02em;
}

/* Controls: one height, one font size, one radius */
.contacts-page .form-control,
.contacts-page .form-select {
    min-height: var(--crm-control-h);
    padding-block: 0.375rem;
    font-size: var(--crm-fs);
}

.contacts-page .form-label {
    margin-bottom: 0.25rem;
    font-size: var(--crm-fs-sm);
    font-weight: 500;
    color: var(--crm-text-muted);
}

.contacts-page .btn:not(.btn-sm) {
    --bs-btn-font-size: var(--crm-fs);
    --bs-btn-font-weight: 500;
    --bs-btn-padding-x: 0.875rem;
    --bs-btn-border-radius: var(--crm-radius);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: var(--crm-control-h);
}

.contacts-page .nav-link {
    padding: 0.4rem 0.875rem;
    font-size: var(--crm-fs);
    font-weight: 500;
}

.contacts-page .badge {
    font-size: var(--crm-fs-xs);
    font-weight: 500;
}

.contacts-page .dropdown-menu,
.row-menu {
    --bs-dropdown-font-size: var(--crm-fs);
    --bs-dropdown-border-radius: var(--crm-radius);
    --bs-dropdown-item-padding-y: 0.4rem;
}

.contacts-page :deep(.pagination) {
    --bs-pagination-font-size: var(--crm-fs);
}

/* Toolbar */
.contacts-search {
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
.contacts-table {
    --bs-table-bg: transparent;
    font-size: var(--crm-fs);
    color: var(--crm-text);
    font-variant-numeric: tabular-nums;
}

.contacts-table thead th {
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

.contacts-table tbody td {
    padding: 0.75rem 1rem;
    white-space: nowrap;
    border-bottom: 1px solid var(--crm-border);
}

.contacts-table tbody tr:last-child td {
    border-bottom: 0;
}

.contact-link {
    color: inherit;
    text-decoration: none;
}

.contact-link:hover {
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

@media print {
    .no-print {
        display: none !important;
    }

    .contacts-page {
        border: 0 !important;
        box-shadow: none !important;
    }

    .contacts-page .table-responsive {
        overflow: visible !important;
        max-height: none !important;
    }
}
</style>
