<script setup>
import { computed, ref } from 'vue';

import { contactsApi } from '@/api/contacts';
import { CONTACT_STATUSES } from '@/constants';
import { useListQuery } from '@/composables/useListQuery';
import { useToastStore } from '@/stores/toast';
import { parseApiError } from '@/utils/errors';
import { formatDate } from '@/utils/format';

import ConfirmModal from '@/components/ConfirmModal.vue';
import LoadingBlock from '@/components/LoadingBlock.vue';
import Pagination from '@/components/Pagination.vue';
import StatusBadge from '@/components/StatusBadge.vue';

const toast = useToastStore();

const {
    filters,
    items,
    meta,
    loading,
    error,
    load,
    goToPage,
    reset,
} = useListQuery(contactsApi.list, {
    search: '',
    status: '',
    sort_by: 'created_at',
    sort_dir: 'desc',
});

const toDelete = ref(null);
const deleting = ref(false);

const selectedIds = ref([]);
const bulkDeleting = ref(false);

const viewMode = ref('table');
const showAdvancedFilters = ref(false);

const searchInput = ref(null);

/*
|--------------------------------------------------------------------------
| Computed
|--------------------------------------------------------------------------
*/

const hasItems = computed(() => items.value.length > 0);

const allSelected = computed(() => {
    return (
        hasItems.value &&
        items.value.every((contact) =>
            selectedIds.value.includes(contact.id)
        )
    );
});

const selectedCount = computed(() => selectedIds.value.length);

const hasSelection = computed(() => selectedCount.value > 0);

const activeFilterCount = computed(() => {
    let count = 0;

    if (filters.status) count++;
    if (filters.sort_by !== 'created_at') count++;
    if (filters.sort_dir !== 'desc') count++;

    return count;
});

/*
|--------------------------------------------------------------------------
| Statistics
|--------------------------------------------------------------------------
|
| These are intentionally calculated from the current page.
| For real CRM dashboards, preferably expose server-side aggregate
| statistics from the API.
|
|--------------------------------------------------------------------------
*/

const totalVisible = computed(() => items.value.length);

const activeVisible = computed(() => {
    return items.value.filter(
        (contact) => contact.status === 'active'
    ).length;
});

const leadVisible = computed(() => {
    return items.value.filter(
        (contact) => contact.status === 'lead'
    ).length;
});

const companyVisible = computed(() => {
    return new Set(
        items.value
            .map((contact) => contact.company)
            .filter(Boolean)
    ).size;
});

/*
|--------------------------------------------------------------------------
| Selection
|--------------------------------------------------------------------------
*/

function toggleSelection(id) {
    if (selectedIds.value.includes(id)) {
        selectedIds.value = selectedIds.value.filter(
            (selectedId) => selectedId !== id
        );

        return;
    }

    selectedIds.value.push(id);
}

function toggleSelectAll() {
    if (allSelected.value) {
        selectedIds.value = [];
        return;
    }

    selectedIds.value = items.value.map((contact) => contact.id);
}

function clearSelection() {
    selectedIds.value = [];
}

/*
|--------------------------------------------------------------------------
| Search
|--------------------------------------------------------------------------
*/

function clearSearch() {
    filters.search = '';

    searchInput.value?.focus();
}

function handleReset() {
    selectedIds.value = [];
    reset();
}

/*
|--------------------------------------------------------------------------
| Refresh
|--------------------------------------------------------------------------
*/

async function refresh() {
    await load();
    toast.success('Contacts refreshed.');
}

/*
|--------------------------------------------------------------------------
| Delete single contact
|--------------------------------------------------------------------------
*/

async function confirmDelete() {
    if (!toDelete.value) {
        return;
    }

    deleting.value = true;

    try {
        await contactsApi.remove(toDelete.value.id);

        toast.success('Contact deleted.');

        toDelete.value = null;

        selectedIds.value = selectedIds.value.filter(
            (id) => id !== toDelete.value?.id
        );

        await load();
    } catch (e) {
        toast.error(parseApiError(e).message);
        toDelete.value = null;
    } finally {
        deleting.value = false;
    }
}

/*
|--------------------------------------------------------------------------
| Bulk delete
|--------------------------------------------------------------------------
*/

async function confirmBulkDelete() {
    if (!selectedIds.value.length) {
        return;
    }

    bulkDeleting.value = true;

    try {
        /*
         * If your backend doesn't yet have a bulk delete endpoint,
         * replace this with Promise.all() temporarily.
         *
         * Recommended backend:
         *
         * contactsApi.bulkRemove(selectedIds.value)
         */
        await Promise.all(
            selectedIds.value.map((id) =>
                contactsApi.remove(id)
            )
        );

        toast.success(
            `${selectedIds.value.length} contact(s) deleted.`
        );

        selectedIds.value = [];

        await load();
    } catch (e) {
        toast.error(parseApiError(e).message);
    } finally {
        bulkDeleting.value = false;
    }
}

/*
|--------------------------------------------------------------------------
| Export
|--------------------------------------------------------------------------
*/

function exportContacts() {
    if (!items.value.length) {
        toast.error('There are no contacts to export.');
        return;
    }

    const headers = [
        'Name',
        'Email',
        'Company',
        'Status',
        'Created',
    ];

    const rows = items.value.map((contact) => [
        contact.full_name,
        contact.email,
        contact.company || '',
        contact.status,
        contact.created_at
            ? formatDate(contact.created_at)
            : '',
    ]);

    const csv = [
        headers,
        ...rows,
    ]
        .map((row) =>
            row
                .map((value) =>
                    `"${String(value ?? '').replaceAll('"', '""')}"`
                )
                .join(',')
        )
        .join('\n');

    const blob = new Blob([csv], {
        type: 'text/csv;charset=utf-8;',
    });

    const url = URL.createObjectURL(blob);

    const link = document.createElement('a');

    link.href = url;
    link.download = `contacts-${new Date()
        .toISOString()
        .slice(0, 10)}.csv`;

    link.click();

    URL.revokeObjectURL(url);

    toast.success('Contacts exported.');
}
</script>

<template>
    <div class="contacts-page">

        <!-- ========================================================= -->
        <!-- HEADER -->
        <!-- ========================================================= -->

        <div
            class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4"
        >
            <div>
                <div class="d-flex align-items-center gap-2 mb-1">
                    <h1 class="h3 fw-bold mb-0">
                        Contacts
                    </h1>

                    <span
                        v-if="meta?.total"
                        class="badge rounded-pill bg-light text-dark border"
                    >
                        {{ meta.total }}
                    </span>
                </div>

                <p class="text-muted mb-0">
                    Manage your customers, leads and business contacts.
                </p>
            </div>

            <div class="d-flex gap-2">

                <!-- Refresh -->
                <button
                    type="button"
                    class="btn btn-light border"
                    :disabled="loading"
                    title="Refresh contacts"
                    @click="refresh"
                >
                    <i
                        class="bi bi-arrow-clockwise"
                        :class="{ 'spin-animation': loading }"
                    ></i>

                    <span class="d-none d-md-inline ms-1">
                        Refresh
                    </span>
                </button>

                <!-- Export -->
                <button
                    type="button"
                    class="btn btn-light border"
                    :disabled="!hasItems"
                    title="Export contacts"
                    @click="exportContacts"
                >
                    <i class="bi bi-download"></i>

                    <span class="d-none d-md-inline ms-1">
                        Export
                    </span>
                </button>

                <!-- Create -->
                <RouterLink
                    :to="{ name: 'contacts.create' }"
                    class="btn btn-primary"
                >
                    <i class="bi bi-plus-lg me-1"></i>
                    New contact
                </RouterLink>
            </div>
        </div>

        <!-- ========================================================= -->
        <!-- KPI CARDS -->
        <!-- ========================================================= -->

        <div class="row g-3 mb-4">

            <!-- Total -->
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">

                        <div
                            class="d-flex justify-content-between align-items-start"
                        >
                            <div>
                                <div class="small text-muted mb-1">
                                    Contacts
                                </div>

                                <div class="fs-3 fw-bold">
                                    {{ meta?.total ?? totalVisible }}
                                </div>

                                <div class="small text-muted mt-1">
                                    Total contacts
                                </div>
                            </div>

                            <div class="crm-stat-icon bg-primary-subtle text-primary">
                                <i class="bi bi-people"></i>
                            </div>
                        </div>

                    </div>
                </div>
            </div>

            <!-- Active -->
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">

                        <div
                            class="d-flex justify-content-between align-items-start"
                        >
                            <div>
                                <div class="small text-muted mb-1">
                                    Active
                                </div>

                                <div class="fs-3 fw-bold">
                                    {{ activeVisible }}
                                </div>

                                <div class="small text-success mt-1">
                                    <i class="bi bi-arrow-up-short"></i>
                                    Active contacts
                                </div>
                            </div>

                            <div class="crm-stat-icon bg-success-subtle text-success">
                                <i class="bi bi-person-check"></i>
                            </div>
                        </div>

                    </div>
                </div>
            </div>

            <!-- Leads -->
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">

                        <div
                            class="d-flex justify-content-between align-items-start"
                        >
                            <div>
                                <div class="small text-muted mb-1">
                                    Leads
                                </div>

                                <div class="fs-3 fw-bold">
                                    {{ leadVisible }}
                                </div>

                                <div class="small text-muted mt-1">
                                    Potential customers
                                </div>
                            </div>

                            <div class="crm-stat-icon bg-warning-subtle text-warning">
                                <i class="bi bi-person-plus"></i>
                            </div>
                        </div>

                    </div>
                </div>
            </div>

            <!-- Companies -->
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">

                        <div
                            class="d-flex justify-content-between align-items-start"
                        >
                            <div>
                                <div class="small text-muted mb-1">
                                    Companies
                                </div>

                                <div class="fs-3 fw-bold">
                                    {{ companyVisible }}
                                </div>

                                <div class="small text-muted mt-1">
                                    Companies on this page
                                </div>
                            </div>

                            <div class="crm-stat-icon bg-info-subtle text-info">
                                <i class="bi bi-building"></i>
                            </div>
                        </div>

                    </div>
                </div>
            </div>

        </div>

        <!-- ========================================================= -->
        <!-- FILTER / TOOLBAR -->
        <!-- ========================================================= -->

        <div class="card border-0 shadow-sm mb-3">

            <div class="card-body">

                <div class="row g-2 align-items-center">

                    <!-- Search -->
                    <div class="col-12 col-lg-5">

                        <div class="crm-search">

                            <i class="bi bi-search"></i>

                            <input
                                ref="searchInput"
                                v-model="filters.search"
                                type="search"
                                class="form-control"
                                placeholder="Search contacts, email or company..."
                            />

                            <button
                                v-if="filters.search"
                                type="button"
                                class="btn btn-sm"
                                title="Clear search"
                                @click="clearSearch"
                            >
                                <i class="bi bi-x-circle"></i>
                            </button>

                        </div>

                    </div>

                    <!-- Status -->
                    <div class="col-12 col-sm-6 col-lg-2">

                        <select
                            v-model="filters.status"
                            class="form-select"
                            aria-label="Filter by status"
                        >
                            <option value="">
                                All statuses
                            </option>

                            <option
                                v-for="status in CONTACT_STATUSES"
                                :key="status.value"
                                :value="status.value"
                            >
                                {{ status.label }}
                            </option>
                        </select>

                    </div>

                    <!-- Advanced -->
                    <div class="col-12 col-sm-6 col-lg-2">

                        <button
                            type="button"
                            class="btn btn-light border w-100 position-relative"
                            @click="showAdvancedFilters = !showAdvancedFilters"
                        >
                            <i class="bi bi-sliders2 me-1"></i>
                            Filters

                            <span
                                v-if="activeFilterCount"
                                class="badge rounded-pill bg-primary ms-1"
                            >
                                {{ activeFilterCount }}
                            </span>
                        </button>

                    </div>

                    <!-- View -->
                    <div class="col-12 col-sm-6 col-lg-2">

                        <div class="btn-group w-100">

                            <button
                                type="button"
                                class="btn"
                                :class="
                                    viewMode === 'table'
                                        ? 'btn-primary'
                                        : 'btn-light border'
                                "
                                title="Table view"
                                @click="viewMode = 'table'"
                            >
                                <i class="bi bi-list"></i>
                            </button>

                            <button
                                type="button"
                                class="btn"
                                :class="
                                    viewMode === 'compact'
                                        ? 'btn-primary'
                                        : 'btn-light border'
                                "
                                title="Compact view"
                                @click="viewMode = 'compact'"
                            >
                                <i class="bi bi-grid-3x3-gap"></i>
                            </button>

                        </div>

                    </div>

                    <!-- Reset -->
                    <div class="col-12 col-sm-6 col-lg-1">

                        <button
                            type="button"
                            class="btn btn-light border w-100"
                            title="Reset filters"
                            @click="handleReset"
                        >
                            <i class="bi bi-arrow-counterclockwise"></i>
                        </button>

                    </div>

                </div>

                <!-- Advanced filters -->
                <div
                    v-if="showAdvancedFilters"
                    class="border-top mt-3 pt-3"
                >

                    <div class="row g-3">

                        <div class="col-md-4">

                            <label class="form-label small text-muted">
                                Sort by
                            </label>

                            <select
                                v-model="filters.sort_by"
                                class="form-select"
                            >
                                <option value="created_at">
                                    Created date
                                </option>

                                <option value="last_name">
                                    Last name
                                </option>

                                <option value="company">
                                    Company
                                </option>
                            </select>

                        </div>

                        <div class="col-md-4">

                            <label class="form-label small text-muted">
                                Order
                            </label>

                            <select
                                v-model="filters.sort_dir"
                                class="form-select"
                            >
                                <option value="desc">
                                    Descending
                                </option>

                                <option value="asc">
                                    Ascending
                                </option>
                            </select>

                        </div>

                        <div class="col-md-4">

                            <label class="form-label small text-muted">
                                Current result
                            </label>

                            <div class="form-control bg-light">
                                {{ items.length }} contacts
                            </div>

                        </div>

                    </div>

                </div>

            </div>

        </div>

        <!-- ========================================================= -->
        <!-- BULK ACTION BAR -->
        <!-- ========================================================= -->

        <div
            v-if="hasSelection"
            class="card border-0 shadow-sm mb-3 border-start border-primary border-4"
        >
            <div
                class="card-body py-2 d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2"
            >

                <div class="small fw-medium">
                    <span class="badge bg-primary rounded-pill me-2">
                        {{ selectedCount }}
                    </span>

                    contact(s) selected
                </div>

                <div class="d-flex gap-2">

                    <button
                        type="button"
                        class="btn btn-sm btn-light border"
                        @click="clearSelection"
                    >
                        Clear
                    </button>

                    <button
                        type="button"
                        class="btn btn-sm btn-outline-danger"
                        :disabled="bulkDeleting"
                        @click="confirmBulkDelete"
                    >
                        <i class="bi bi-trash me-1"></i>

                        {{
                            bulkDeleting
                                ? 'Deleting...'
                                : 'Delete selected'
                        }}
                    </button>

                </div>

            </div>
        </div>

        <!-- ========================================================= -->
        <!-- TABLE -->
        <!-- ========================================================= -->

        <div class="card border-0 shadow-sm">

            <!-- Error -->
            <div
                v-if="error"
                class="alert alert-danger m-3 mb-0"
                role="alert"
            >
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-exclamation-triangle"></i>
                    <span>{{ error }}</span>
                </div>
            </div>

            <!-- Loading -->
            <LoadingBlock
                v-else-if="loading && !items.length"
            />

            <!-- Data -->
            <div
                v-else
                class="table-responsive"
                :style="{
                    opacity: loading ? 0.55 : 1,
                    transition: 'opacity .2s ease'
                }"
            >

                <table
                    class="table table-hover align-middle mb-0 crm-table"
                >

                    <thead class="table-light">

                        <tr>

                            <!-- Checkbox -->
                            <th
                                class="ps-3"
                                style="width: 45px;"
                            >
                                <div class="form-check">
                                    <input
                                        class="form-check-input"
                                        type="checkbox"
                                        :checked="allSelected"
                                        @change="toggleSelectAll"
                                    />
                                </div>
                            </th>

                            <th>
                                Contact
                            </th>

                            <th class="d-none d-lg-table-cell">
                                Company
                            </th>

                            <th class="d-none d-md-table-cell">
                                Status
                            </th>

                            <th class="d-none d-xl-table-cell">
                                Created
                            </th>

                            <th
                                class="text-end pe-3"
                                style="width: 150px;"
                            >
                                Actions
                            </th>

                        </tr>

                    </thead>

                    <tbody>

                        <!-- Empty -->
                        <tr v-if="!items.length">

                            <td
                                colspan="6"
                                class="py-5"
                            >

                                <div class="crm-empty-state">

                                    <div class="crm-empty-icon">
                                        <i class="bi bi-people"></i>
                                    </div>

                                    <h5 class="fw-semibold mb-1">
                                        No contacts found
                                    </h5>

                                    <p class="text-muted mb-3">
                                        Try changing your filters or create
                                        your first contact.
                                    </p>

                                    <div class="d-flex justify-content-center gap-2">

                                        <button
                                            type="button"
                                            class="btn btn-light border"
                                            @click="handleReset"
                                        >
                                            Reset filters
                                        </button>

                                        <RouterLink
                                            :to="{ name: 'contacts.create' }"
                                            class="btn btn-primary"
                                        >
                                            <i class="bi bi-plus-lg me-1"></i>
                                            Add contact
                                        </RouterLink>

                                    </div>

                                </div>

                            </td>

                        </tr>

                        <!-- Rows -->
                        <tr
                            v-for="contact in items"
                            :key="contact.id"
                            :class="{
                                'crm-row-selected':
                                    selectedIds.includes(contact.id)
                            }"
                        >

                            <!-- Checkbox -->
                            <td class="ps-3">

                                <div class="form-check">

                                    <input
                                        class="form-check-input"
                                        type="checkbox"
                                        :checked="
                                            selectedIds.includes(contact.id)
                                        "
                                        @change="
                                            toggleSelection(contact.id)
                                        "
                                    />

                                </div>

                            </td>

                            <!-- Contact -->
                            <td>

                                <div
                                    class="d-flex align-items-center gap-3"
                                >

                                    <div class="crm-avatar">
                                        {{
                                            contact.full_name
                                                ?.charAt(0)
                                                ?.toUpperCase() || '?'
                                        }}
                                    </div>

                                    <div class="min-w-0">

                                        <RouterLink
                                            :to="{
                                                name: 'contacts.show',
                                                params: {
                                                    id: contact.id
                                                }
                                            }"
                                            class="fw-semibold text-dark text-decoration-none d-block text-truncate"
                                            style="max-width: 260px;"
                                        >
                                            {{ contact.full_name }}
                                        </RouterLink>

                                        <div
                                            class="small text-muted text-truncate"
                                            style="max-width: 260px;"
                                        >
                                            <i
                                                class="bi bi-envelope me-1"
                                            ></i>

                                            {{ contact.email || 'No email' }}
                                        </div>

                                    </div>

                                </div>

                            </td>

                            <!-- Company -->
                            <td class="d-none d-lg-table-cell">

                                <div
                                    v-if="contact.company"
                                    class="d-flex align-items-center gap-2"
                                >
                                    <span class="crm-company-icon">
                                        <i class="bi bi-building"></i>
                                    </span>

                                    <span>
                                        {{ contact.company }}
                                    </span>
                                </div>

                                <span
                                    v-else
                                    class="text-muted"
                                >
                                    —
                                </span>

                            </td>

                            <!-- Status -->
                            <td class="d-none d-md-table-cell">

                                <StatusBadge
                                    kind="contact"
                                    :value="contact.status"
                                />

                            </td>

                            <!-- Created -->
                            <td class="d-none d-xl-table-cell">

                                <div class="small">
                                    {{ formatDate(contact.created_at) }}
                                </div>

                                <div class="small text-muted">
                                    Created
                                </div>

                            </td>

                            <!-- Actions -->
                            <td class="text-end pe-3">

                                <div class="d-inline-flex gap-1">

                                    <!-- View -->
                                    <RouterLink
                                        :to="{
                                            name: 'contacts.show',
                                            params: {
                                                id: contact.id
                                            }
                                        }"
                                        class="btn btn-sm btn-light border"
                                        title="View contact"
                                    >
                                        <i class="bi bi-eye"></i>
                                    </RouterLink>

                                    <!-- Edit -->
                                    <RouterLink
                                        :to="{
                                            name: 'contacts.edit',
                                            params: {
                                                id: contact.id
                                            }
                                        }"
                                        class="btn btn-sm btn-light border"
                                        title="Edit contact"
                                    >
                                        <i class="bi bi-pencil"></i>
                                    </RouterLink>

                                    <!-- Delete -->
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-light border text-danger"
                                        title="Delete contact"
                                        @click="toDelete = contact"
                                    >
                                        <i class="bi bi-trash"></i>
                                    </button>

                                </div>

                            </td>

                        </tr>

                    </tbody>

                </table>

            </div>

            <!-- Pagination -->
            <div
                v-if="meta"
                class="card-footer bg-white border-top d-flex justify-content-between align-items-center"
            >

                <div class="small text-muted d-none d-md-block">
                    Showing
                    <strong>{{ meta.from ?? 0 }}</strong>
                    –
                    <strong>{{ meta.to ?? 0 }}</strong>
                    of
                    <strong>{{ meta.total ?? 0 }}</strong>
                </div>

                <Pagination
                    :meta="meta"
                    @change="goToPage"
                />

            </div>

        </div>

        <!-- ========================================================= -->
        <!-- DELETE MODAL -->
        <!-- ========================================================= -->

        <ConfirmModal
            :show="!!toDelete"
            title="Delete contact"
            :message="
                `Delete ${toDelete?.full_name}? This can't be undone from the panel.`
            "
            :loading="deleting"
            @confirm="confirmDelete"
            @cancel="toDelete = null"
        />

    </div>
</template>

<style scoped>
.contacts-page {
    --crm-border: #e9ecef;
    --crm-muted: #6c757d;
}

/* ---------------------------------------------------------------
   Search
--------------------------------------------------------------- */

.crm-search {
    position: relative;
    display: flex;
    align-items: center;
}

.crm-search > i {
    position: absolute;
    left: 14px;
    z-index: 2;
    color: var(--crm-muted);
    pointer-events: none;
}

.crm-search input {
    padding-left: 40px;
    padding-right: 40px;
}

.crm-search button {
    position: absolute;
    right: 6px;
    color: var(--crm-muted);
    z-index: 2;
}

/* ---------------------------------------------------------------
   KPI
--------------------------------------------------------------- */

.crm-stat-icon {
    width: 44px;
    height: 44px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 12px;
    font-size: 20px;
}

/* ---------------------------------------------------------------
   Avatar
--------------------------------------------------------------- */

.crm-avatar {
    width: 42px;
    height: 42px;
    min-width: 42px;

    display: flex;
    align-items: center;
    justify-content: center;

    border-radius: 50%;

    background: #eef2ff;
    color: #4f46e5;

    font-weight: 700;
    font-size: 15px;
}

/* ---------------------------------------------------------------
   Company icon
--------------------------------------------------------------- */

.crm-company-icon {
    width: 30px;
    height: 30px;

    display: inline-flex;
    align-items: center;
    justify-content: center;

    border-radius: 8px;

    background: #f8f9fa;
    color: #6c757d;
}

/* ---------------------------------------------------------------
   Table
--------------------------------------------------------------- */

.crm-table th {
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .03em;
    color: #6c757d;
    white-space: nowrap;
}

.crm-table td {
    border-color: var(--crm-border);
}

.crm-table tbody tr {
    transition:
        background-color .15s ease,
        box-shadow .15s ease;
}

.crm-table tbody tr:hover {
    background-color: #fafbfc;
}

.crm-row-selected {
    background-color: #f0f5ff !important;
}

/* ---------------------------------------------------------------
   Empty state
--------------------------------------------------------------- */

.crm-empty-state {
    text-align: center;
    max-width: 420px;
    margin: 0 auto;
}

.crm-empty-icon {
    width: 64px;
    height: 64px;

    margin: 0 auto 16px;

    display: flex;
    align-items: center;
    justify-content: center;

    border-radius: 18px;

    background: #f1f3f5;
    color: #6c757d;

    font-size: 28px;
}

/* ---------------------------------------------------------------
   Loading
--------------------------------------------------------------- */

.spin-animation {
    animation: crm-spin .8s linear infinite;
}

@keyframes crm-spin {
    to {
        transform: rotate(360deg);
    }
}

/* ---------------------------------------------------------------
   Responsive
--------------------------------------------------------------- */

@media (max-width: 767.98px) {
    .crm-table th,
    .crm-table td {
        white-space: nowrap;
    }

    .crm-avatar {
        width: 36px;
        height: 36px;
        min-width: 36px;
    }

    .crm-stat-icon {
        width: 38px;
        height: 38px;
    }
}
</style>
