<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref, unref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { rolesApi } from '@/api/access';
import { useColumnVisibility } from '@/composables/useColumnVisibility';
import { useListQuery } from '@/composables/useListQuery';
import { usePermission } from '@/composables/usePermission';
import { useToastStore } from '@/stores/toast';
import { formatDate } from '@/utils/format';
import { parseApiError } from '@/utils/errors';
import ConfirmModal from '@/components/ConfirmModal.vue';
import DeleteConfirmModal from '@/components/DeleteConfirmModal.vue';
import LoadingBlock from '@/components/LoadingBlock.vue';
import Pagination from '@/components/Pagination.vue';
import RecordDrawer from '@/components/RecordDrawer.vue';
import RoleFormView from '@/views/roles/RoleFormView.vue';

const toast = useToastStore();
const route = useRoute();
const router = useRouter();
const { can } = usePermission();

/* -------------------------------------------------------------------------- */
/* Static config                                                              */
/* -------------------------------------------------------------------------- */

const DEFAULT_FILTERS = { search: '', is_system: '', sort: 'name', direction: 'asc' };

const SORT_OPTIONS = [
    { value: 'name', label: 'Role name' },
    { value: 'users_count', label: 'Users' },
    { value: 'permissions_count', label: 'Permissions' },
    { value: 'created_at', label: 'Created' },
];

// Order here = order of table columns and of the Columns dropdown.
const COLUMNS = [
    { key: 'id', label: 'Id', default: true },
    { key: 'name', label: 'Role', locked: true, default: true },
    { key: 'description', label: 'Description', default: true },
    { key: 'type', label: 'Type', default: true },
    { key: 'permissions', label: 'Permissions', default: true },
    { key: 'users', label: 'Users', default: true },
    { key: 'created_at', label: 'Created', default: false },
];

const { visible: visibleColumns, shown: shownColumns } = useColumnVisibility('roles.visibleColumns.v1', COLUMNS);

const isRight = (key) => key === 'permissions' || key === 'users';

/* -------------------------------------------------------------------------- */
/* Data                                                                       */
/* -------------------------------------------------------------------------- */

const { filters, items, meta, loading, error, load, goToPage, reset } = useListQuery(rolesApi.list, { ...DEFAULT_FILTERS });

const summary = ref({ total: 0, custom: 0, permissions: 0, users_without_role: 0 });
const groups = ref([]);

async function loadSummary() {
    try {
        summary.value = await rolesApi.summary();
    } catch {
        /* tabs and KPIs just show no figures; the list itself reports its own errors */
    }
}

async function loadGroups() {
    try {
        groups.value = await rolesApi.permissions();
    } catch {
        /* the form shows an empty matrix with a hint */
    }
}

const refresh = () => Promise.all([load(), loadSummary()]);

const rowOffset = computed(() => {
    const m = unref(meta);
    if (!m) return 0;
    if (Number.isInteger(m.from)) return m.from - 1;
    return ((m.current_page ?? 1) - 1) * (m.per_page ?? 0);
});

const kpis = computed(() => {
    const s = summary.value;
    return [
        { key: 'roles', label: 'Roles', value: s.total ?? 0, hint: `${s.custom ?? 0} custom` },
        { key: 'permissions', label: 'Permissions', value: s.permissions ?? 0, hint: 'Across all modules' },
        { key: 'unassigned', label: 'Users without a role', value: s.users_without_role ?? 0, hint: 'Cannot access protected pages' },
    ];
});

const TABS = computed(() => [
    { value: '', label: 'All', count: summary.value.total ?? 0 },
    { value: '1', label: 'System', count: (summary.value.total ?? 0) - (summary.value.custom ?? 0) },
    { value: '0', label: 'Custom', count: summary.value.custom ?? 0 },
]);

/* -------------------------------------------------------------------------- */
/* Filter panel + columns dropdown                                            */
/* -------------------------------------------------------------------------- */

const filterOpen = ref(false);
const filterRef = ref(null);
const columnsOpen = ref(false);
const columnsRef = ref(null);

const activeFilterCount = computed(() => {
    const f = unref(filters);
    return f.sort !== DEFAULT_FILTERS.sort || f.direction !== DEFAULT_FILTERS.direction ? 1 : 0;
});

/* -------------------------------------------------------------------------- */
/* Row actions menu (teleported so the scrolling table can't clip it)         */
/* -------------------------------------------------------------------------- */

const menu = ref({ role: null, top: 0, right: 0 });

function closeMenu() {
    menu.value = { role: null, top: 0, right: 0 };
}

function toggleMenu(event, role) {
    if (menu.value.role?.id === role.id) return closeMenu();

    const rect = event.currentTarget.getBoundingClientRect();
    menu.value = { role, top: rect.bottom + 4, right: window.innerWidth - rect.right };
}

const canDelete = (role) => can('roles.delete') && !role.is_system;

/* -------------------------------------------------------------------------- */
/* Delete                                                                     */
/* -------------------------------------------------------------------------- */

const toDelete = ref(null);
const deleting = ref(false);

function askDelete(role) {
    closeMenu();
    toDelete.value = role;
}

async function confirmDelete() {
    deleting.value = true;
    try {
        const wasLastRowOnPage = items.value.length === 1;
        const page = unref(meta)?.current_page ?? 1;

        await rolesApi.remove(toDelete.value.id);
        toast.success('Role deleted.');
        toDelete.value = null;

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
/* Create / edit / view drawer (driven by the route, so it is linkable and survives refresh) */
/* -------------------------------------------------------------------------- */

const drawer = reactive({ show: false, roleId: null, readonly: false });
const formRef = ref(null);
const confirmDiscard = ref(false);

watch(
    () => [route.name, route.params.id],
    ([name, id]) => {
        drawer.show = ['roles.create', 'roles.edit', 'roles.show'].includes(name);
        if (!drawer.show) return;
        drawer.roleId = name === 'roles.create' ? null : String(id);
        drawer.readonly = name === 'roles.show';
    },
    { immediate: true },
);

const drawerTitle = computed(() => (drawer.roleId === null ? 'Create Role' : drawer.readonly ? 'Role permissions' : 'Edit Role'));

function closeDrawer() {
    confirmDiscard.value = false;
    router.replace({ name: 'roles.index', query: route.query });
}

function requestClose() {
    if (formRef.value?.isDirty) {
        confirmDiscard.value = true;
        return;
    }
    closeDrawer();
}

async function onRoleSaved(saved, { another } = {}) {
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
    if (menu.value.role && !target.closest?.('.row-menu, .row-menu-toggle')) closeMenu();
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
    loadGroups();
});

onBeforeUnmount(() => {
    document.removeEventListener('click', onDocumentClick);
    document.removeEventListener('keydown', onKeydown);
    window.removeEventListener('resize', closeMenu);
    window.removeEventListener('scroll', closeMenu, true);
});
</script>

<template>
    <div class="card roles-page rounded-3 overflow-visible">
        <!-- Header -->
        <div class="card-header bg-white d-flex justify-content-between align-items-center px-3 py-3 rounded-top-3">
            <h1 class="roles-title mb-0">Roles &amp; Permissions</h1>
            <RouterLink v-can="'roles.create'" :to="{ name: 'roles.create' }" class="btn btn-primary no-print">
                <i class="bi bi-plus-lg me-2"></i>Create Role
            </RouterLink>
        </div>

        <!-- KPIs -->
        <section class="roles-kpis" aria-label="Access summary">
            <div v-for="k in kpis" :key="k.key" class="roles-kpi">
                <span class="roles-kpi__label">{{ k.label }}</span>
                <strong class="roles-kpi__value">{{ k.value }}</strong>
                <span class="roles-kpi__hint">{{ k.hint }}</span>
            </div>
        </section>

        <!-- Type tabs -->
        <ul class="nav nav-pills flex-nowrap overflow-auto gap-1 px-3 pt-3 pb-3 no-print">
            <li v-for="t in TABS" :key="t.value" class="nav-item">
                <button type="button" class="nav-link text-nowrap" :class="{ active: filters.is_system === t.value }" @click="filters.is_system = t.value">
                    {{ t.label }}
                    <span class="badge rounded-pill ms-1" :class="filters.is_system === t.value ? 'text-bg-light' : 'text-bg-secondary'">{{ t.count }}</span>
                </button>
            </li>
        </ul>

        <!-- Toolbar -->
        <div class="roles-toolbar d-flex flex-wrap align-items-center gap-2 px-3 py-3 bg-light border-top border-bottom no-print">
            <form class="input-group roles-search" role="search" @submit.prevent="load">
                <input v-model.trim="filters.search" type="search" class="form-control" placeholder="Search" aria-label="Search roles" />
                <button type="submit" class="btn btn-primary" aria-label="Search"><i class="bi bi-search"></i></button>
            </form>

            <!-- Filter -->
            <div ref="filterRef" class="position-relative">
                <button type="button" class="btn btn-outline-secondary bg-white text-body" aria-haspopup="dialog" :aria-expanded="filterOpen" @click="filterOpen = !filterOpen">
                    <i class="bi bi-funnel me-2"></i>Filter
                    <span v-if="activeFilterCount" class="badge text-bg-primary ms-2">{{ activeFilterCount }}</span>
                    <i class="bi bi-plus-lg ms-3"></i>
                </button>

                <div v-if="filterOpen" class="card shadow filter-panel" role="dialog" aria-label="Filter roles">
                    <div class="card-body d-flex flex-column gap-3">
                        <div class="row g-2">
                            <div class="col-7">
                                <label for="role-filter-sort" class="form-label small text-muted mb-1">Sort by</label>
                                <select id="role-filter-sort" v-model="filters.sort" class="form-select">
                                    <option v-for="o in SORT_OPTIONS" :key="o.value" :value="o.value">{{ o.label }}</option>
                                </select>
                            </div>
                            <div class="col-5">
                                <label for="role-filter-order" class="form-label small text-muted mb-1">Order</label>
                                <select id="role-filter-order" v-model="filters.direction" class="form-select">
                                    <option value="asc">Ascending</option>
                                    <option value="desc">Descending</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer bg-white d-flex justify-content-between align-items-center">
                        <button type="button" class="btn btn-link text-decoration-none px-0" @click="reset"><i class="bi bi-x-lg me-1"></i>Reset all</button>
                        <button type="button" class="btn btn-primary" @click="filterOpen = false">Done</button>
                    </div>
                </div>
            </div>

            <!-- Columns -->
            <div ref="columnsRef" class="position-relative">
                <button type="button" class="btn btn-outline-secondary bg-white text-body" aria-haspopup="true" :aria-expanded="columnsOpen" @click="columnsOpen = !columnsOpen">
                    Columns<i class="bi bi-chevron-down ms-4 small"></i>
                </button>

                <ul v-if="columnsOpen" class="dropdown-menu show p-2 shadow mt-1">
                    <li v-for="c in COLUMNS" :key="c.key">
                        <div class="form-check mx-2 my-1">
                            <input :id="`role-col-${c.key}`" v-model="visibleColumns" type="checkbox" class="form-check-input" :value="c.key" :disabled="c.locked" />
                            <label :for="`role-col-${c.key}`" class="form-check-label w-100 text-nowrap">{{ c.label }}</label>
                        </div>
                    </li>
                </ul>
            </div>

            <div class="ms-auto d-flex gap-2">
                <button v-can="'users.view'" type="button" class="btn btn-light border shadow-sm" @click="$router.push({ name: 'access.users' })">
                    <i class="bi bi-people me-2"></i>User access
                </button>
            </div>
        </div>

        <!-- Table -->
        <div v-if="error" class="alert alert-danger m-3">{{ error }}</div>
        <LoadingBlock v-else-if="loading && !items.length" />

        <div v-else class="table-responsive roles-scroll" :style="{ opacity: loading ? 0.6 : 1 }">
            <table class="table align-middle mb-0 roles-table">
                <thead>
                    <tr>
                        <th v-for="c in shownColumns" :key="c.key" scope="col" :class="{ 'text-end': isRight(c.key) }">{{ c.label }}</th>
                        <th scope="col" class="no-print"><span class="visually-hidden">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="!items.length">
                        <td :colspan="shownColumns.length + 1" class="text-center text-muted py-5">No roles found.</td>
                    </tr>

                    <tr v-for="(r, index) in items" :key="r.id">
                        <td v-for="col in shownColumns" :key="col.key" :class="{ 'text-end': isRight(col.key) }">
                            <template v-if="col.key === 'id'">{{ rowOffset + index + 1 }}</template>

                            <RouterLink v-else-if="col.key === 'name'" :to="{ name: 'roles.show', params: { id: r.id } }" class="role-link">{{ r.name }}</RouterLink>

                            <span v-else-if="col.key === 'description'" class="role-desc" :title="r.description">{{ r.description || '—' }}</span>

                            <span v-else-if="col.key === 'type'" class="badge" :class="r.is_system ? 'text-bg-primary' : 'text-bg-secondary'">
                                {{ r.is_system ? 'System' : 'Custom' }}
                            </span>

                            <template v-else-if="col.key === 'permissions'">
                                <span v-if="r.is_super_admin" class="badge text-bg-success">All</span>
                                <template v-else>{{ r.permissions_count }}</template>
                            </template>

                            <template v-else-if="col.key === 'users'">{{ r.users_count }}</template>
                            <template v-else-if="col.key === 'created_at'">{{ formatDate(r.created_at) }}</template>
                        </td>

                        <td class="text-end no-print">
                            <button
                                type="button"
                                class="btn btn-sm btn-link text-body row-menu-toggle"
                                aria-haspopup="menu"
                                :aria-expanded="menu.role?.id === r.id"
                                :aria-label="`Actions for ${r.name}`"
                                @click="toggleMenu($event, r)"
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

    <!-- Create / edit / view drawer -->
    <RecordDrawer :show="drawer.show" :title="drawerTitle" @close="requestClose">
        <RoleFormView
            ref="formRef"
            :key="route.fullPath"
            :role-id="drawer.roleId"
            :groups="groups"
            :readonly="drawer.readonly"
            @saved="onRoleSaved"
            @cancel="closeDrawer"
        />
    </RecordDrawer>

    <!-- Row actions menu -->
    <Teleport to="body">
        <ul v-if="menu.role" class="dropdown-menu show row-menu shadow" role="menu" :style="{ top: `${menu.top}px`, right: `${menu.right}px` }">
            <li>
                <RouterLink class="dropdown-item" role="menuitem" :to="{ name: 'roles.show', params: { id: menu.role.id } }" @click="closeMenu">
                    <i class="bi bi-eye me-2"></i>View
                </RouterLink>
            </li>
            <li v-if="can('roles.edit')">
                <RouterLink class="dropdown-item" role="menuitem" :to="{ name: 'roles.edit', params: { id: menu.role.id } }" @click="closeMenu">
                    <i class="bi bi-pencil me-2"></i>Edit
                </RouterLink>
            </li>
            <template v-if="canDelete(menu.role)">
                <li><hr class="dropdown-divider" /></li>
                <li>
                    <button type="button" class="dropdown-item text-danger" role="menuitem" @click="askDelete(menu.role)">
                        <i class="bi bi-trash me-2"></i>Delete
                    </button>
                </li>
            </template>
        </ul>
    </Teleport>

    <DeleteConfirmModal
        :show="!!toDelete"
        title="Delete role"
        :message="`Delete ${toDelete?.name}? ${toDelete?.users_count ? `${toDelete.users_count} user(s) still have this role.` : ''}`"
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
/* All sizes, colours and fonts come from the global --crm-* tokens (resources/css/layout.css). */
.roles-page {
    font-family: var(--crm-font);
    font-size: var(--crm-fs);
    line-height: 1.5;
    color: var(--crm-text);
}

.roles-title {
    font-size: 1.5rem;
    font-weight: 600;
    letter-spacing: -0.02em;
}

.roles-page .form-control,
.roles-page .form-select {
    min-height: var(--crm-control-h);
    padding-block: 0.375rem;
    font-size: var(--crm-fs);
}

.roles-page .btn:not(.btn-sm) {
    --bs-btn-font-size: var(--crm-fs);
    --bs-btn-font-weight: 500;
    --bs-btn-padding-x: 0.875rem;
    --bs-btn-border-radius: var(--crm-radius);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: var(--crm-control-h);
}

.roles-page .nav-link {
    padding: 0.4rem 0.875rem;
    font-size: var(--crm-fs);
    font-weight: 500;
}

.roles-page .badge {
    font-size: var(--crm-fs-xs);
    font-weight: 500;
}

.roles-page .dropdown-menu,
.row-menu {
    --bs-dropdown-font-size: var(--crm-fs);
    --bs-dropdown-border-radius: var(--crm-radius);
    --bs-dropdown-item-padding-y: 0.4rem;
}

.roles-page :deep(.pagination) {
    --bs-pagination-font-size: var(--crm-fs);
}

/* KPIs */
.roles-kpis {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(12rem, 1fr));
    border-bottom: 1px solid var(--crm-border);
}

.roles-kpi {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    padding: 1rem 1.25rem;
}

.roles-kpi + .roles-kpi {
    border-left: 1px solid var(--crm-border);
}

.roles-kpi__label {
    font-size: var(--crm-fs-sm);
    font-weight: 500;
    color: var(--crm-text-muted);
}

.roles-kpi__value {
    font-size: 1.125rem;
    font-weight: 600;
    font-variant-numeric: tabular-nums;
    line-height: 1.4;
}

.roles-kpi__hint {
    font-size: var(--crm-fs-xs);
    color: var(--crm-text-muted);
}

/* Toolbar */
.roles-search {
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

/* Table */
.roles-table {
    --bs-table-bg: transparent;
    font-size: var(--crm-fs);
    color: var(--crm-text);
    font-variant-numeric: tabular-nums;
}

.roles-table thead th {
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
    box-shadow: inset 0 -1px 0 var(--crm-border);
}

.roles-table tbody td {
    padding: 0.75rem 1rem;
    white-space: nowrap;
    border-bottom: 1px solid var(--crm-border);
}

.roles-table tbody tr:last-child td {
    border-bottom: 0;
}

.role-desc {
    display: inline-block;
    max-width: 28rem;
    overflow: hidden;
    color: var(--crm-text-muted);
    text-overflow: ellipsis;
    vertical-align: bottom;
}

.role-link {
    color: inherit;
    text-decoration: none;
}

.role-link:hover {
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
    .roles-kpi + .roles-kpi {
        border-top: 1px solid var(--crm-border);
        border-left: 0;
    }
}

@media print {
    .no-print {
        display: none !important;
    }

    .roles-page {
        border: 0 !important;
        box-shadow: none !important;
    }
}
</style>
