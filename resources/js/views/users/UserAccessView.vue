<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref, unref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { rolesApi, userAccessApi } from '@/api/access';
import { useColumnVisibility } from '@/composables/useColumnVisibility';
import { useListQuery } from '@/composables/useListQuery';
import { usePermission } from '@/composables/usePermission';
import { formatDate } from '@/utils/format';
import ConfirmModal from '@/components/ConfirmModal.vue';
import LoadingBlock from '@/components/LoadingBlock.vue';
import Pagination from '@/components/Pagination.vue';
import RecordDrawer from '@/components/RecordDrawer.vue';
import UserRolesForm from '@/views/users/UserRolesForm.vue';

const route = useRoute();
const router = useRouter();
const { can } = usePermission();

/* -------------------------------------------------------------------------- */
/* Static config                                                              */
/* -------------------------------------------------------------------------- */

const DEFAULT_FILTERS = { search: '', role_id: '', no_role: '', sort: 'name', direction: 'asc' };

const SORT_OPTIONS = [
    { value: 'name', label: 'Name' },
    { value: 'email', label: 'E-mail' },
    { value: 'created_at', label: 'Joined' },
];

const COLUMNS = [
    { key: 'id', label: 'Id', default: true },
    { key: 'name', label: 'User', locked: true, default: true },
    { key: 'email', label: 'E-mail', default: true },
    { key: 'roles', label: 'Roles', default: true },
    { key: 'created_at', label: 'Joined', default: false },
];

const { visible: visibleColumns, shown: shownColumns } = useColumnVisibility('access.users.visibleColumns.v1', COLUMNS);

/* -------------------------------------------------------------------------- */
/* Data                                                                       */
/* -------------------------------------------------------------------------- */

const { filters, items, meta, loading, error, load, goToPage, reset } = useListQuery(userAccessApi.list, { ...DEFAULT_FILTERS });

const summary = ref({ total: 0, with_roles: 0, without_roles: 0, super_admins: 0 });
const roles = ref([]);
const groups = ref([]);   // permission matrix for the access form

async function loadSummary() {
    try {
        summary.value = await userAccessApi.summary();
    } catch {
        /* KPIs just show no figures */
    }
}

async function loadGroups() {
    try {
        groups.value = await rolesApi.permissions();
    } catch {
        /* the form still assigns roles; the matrix just stays empty */
    }
}

async function loadRoles() {
    try {
        roles.value = (await userAccessApi.lookups()).roles ?? [];
    } catch {
        /* the filter and the form just stay empty */
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
        { key: 'total', label: 'Users', value: s.total ?? 0, hint: `${s.with_roles ?? 0} with a role` },
        { key: 'none', label: 'Without a role', value: s.without_roles ?? 0, hint: 'Cannot access protected pages' },
        { key: 'super', label: 'Super admins', value: s.super_admins ?? 0, hint: 'Unrestricted access' },
    ];
});

const TABS = computed(() => [
    { key: 'all', label: 'All', count: summary.value.total ?? 0, active: filters.no_role === '', pick: () => (filters.no_role = '') },
    { key: 'none', label: 'No role', count: summary.value.without_roles ?? 0, active: filters.no_role === '1', pick: () => (filters.no_role = '1') },
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
    const sorted = f.sort !== DEFAULT_FILTERS.sort || f.direction !== DEFAULT_FILTERS.direction;
    return (f.role_id !== '' ? 1 : 0) + (sorted ? 1 : 0);
});

/* -------------------------------------------------------------------------- */
/* Row actions menu                                                           */
/* -------------------------------------------------------------------------- */

const menu = ref({ user: null, top: 0, right: 0 });

function closeMenu() {
    menu.value = { user: null, top: 0, right: 0 };
}

function toggleMenu(event, user) {
    if (menu.value.user?.id === user.id) return closeMenu();

    const rect = event.currentTarget.getBoundingClientRect();
    menu.value = { user, top: rect.bottom + 4, right: window.innerWidth - rect.right };
}

/* -------------------------------------------------------------------------- */
/* Assign-roles drawer (route-driven, like the other modules)                 */
/* -------------------------------------------------------------------------- */

const drawer = reactive({ show: false, userId: null });
const formRef = ref(null);
const confirmDiscard = ref(false);

watch(
    () => [route.name, route.params.id],
    ([name, id]) => {
        drawer.show = name === 'access.users.edit' || name === 'access.users.create';
        drawer.userId = name === 'access.users.edit' ? String(id) : null;
    },
    { immediate: true },
);

function closeDrawer() {
    confirmDiscard.value = false;
    router.replace({ name: 'access.users', query: route.query });
}

function requestClose() {
    if (formRef.value?.isDirty) {
        confirmDiscard.value = true;
        return;
    }
    closeDrawer();
}

async function onSaved() {
    await refresh();
    closeDrawer();
}

/* -------------------------------------------------------------------------- */
/* Global listeners                                                           */
/* -------------------------------------------------------------------------- */

function onDocumentClick(event) {
    const target = event.target;
    if (filterOpen.value && !filterRef.value?.contains(target)) filterOpen.value = false;
    if (columnsOpen.value && !columnsRef.value?.contains(target)) columnsOpen.value = false;
    if (menu.value.user && !target.closest?.('.row-menu, .row-menu-toggle')) closeMenu();
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
    loadRoles();
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
    <div class="card access-page rounded-3 overflow-visible">
        <!-- Header -->
        <div class="card-header bg-white d-flex justify-content-between align-items-center px-3 py-3 rounded-top-3">
            <h1 class="access-title mb-0">User Access</h1>
            <div class="d-flex align-items-center gap-2">
                <RouterLink v-can="'roles.view'" :to="{ name: 'roles.index' }" class="btn btn-outline-secondary bg-white text-body no-print">
                    <i class="bi bi-shield-lock me-2"></i>Roles &amp; Permissions
                </RouterLink>
                <RouterLink v-can="'users.create'" :to="{ name: 'access.users.create' }" class="btn btn-primary no-print">
                    <i class="bi bi-person-plus me-2"></i>New user
                </RouterLink>
            </div>
        </div>

        <!-- KPIs -->
        <section class="access-kpis" aria-label="User access summary">
            <div v-for="k in kpis" :key="k.key" class="access-kpi">
                <span class="access-kpi__label">{{ k.label }}</span>
                <strong class="access-kpi__value">{{ k.value }}</strong>
                <span class="access-kpi__hint">{{ k.hint }}</span>
            </div>
        </section>

        <!-- Tabs -->
        <ul class="nav nav-pills flex-nowrap overflow-auto gap-1 px-3 pt-3 pb-3 no-print">
            <li v-for="t in TABS" :key="t.key" class="nav-item">
                <button type="button" class="nav-link text-nowrap" :class="{ active: t.active }" @click="t.pick">
                    {{ t.label }}
                    <span class="badge rounded-pill ms-1" :class="t.active ? 'text-bg-light' : 'text-bg-secondary'">{{ t.count }}</span>
                </button>
            </li>
        </ul>

        <!-- Toolbar -->
        <div class="access-toolbar d-flex flex-wrap align-items-center gap-2 px-3 py-3 bg-light border-top border-bottom no-print">
            <form class="input-group access-search" role="search" @submit.prevent="load">
                <input v-model.trim="filters.search" type="search" class="form-control" placeholder="Search" aria-label="Search users" />
                <button type="submit" class="btn btn-primary" aria-label="Search"><i class="bi bi-search"></i></button>
            </form>

            <!-- Filter -->
            <div ref="filterRef" class="position-relative">
                <button type="button" class="btn btn-outline-secondary bg-white text-body" aria-haspopup="dialog" :aria-expanded="filterOpen" @click="filterOpen = !filterOpen">
                    <i class="bi bi-funnel me-2"></i>Filter
                    <span v-if="activeFilterCount" class="badge text-bg-primary ms-2">{{ activeFilterCount }}</span>
                    <i class="bi bi-plus-lg ms-3"></i>
                </button>

                <div v-if="filterOpen" class="card shadow filter-panel" role="dialog" aria-label="Filter users">
                    <div class="card-body d-flex flex-column gap-3">
                        <div>
                            <label for="access-filter-role" class="form-label small text-muted mb-1">Role</label>
                            <select id="access-filter-role" v-model="filters.role_id" class="form-select">
                                <option value="">All</option>
                                <option v-for="r in roles" :key="r.id" :value="String(r.id)">{{ r.name }}</option>
                            </select>
                        </div>

                        <div class="row g-2">
                            <div class="col-7">
                                <label for="access-filter-sort" class="form-label small text-muted mb-1">Sort by</label>
                                <select id="access-filter-sort" v-model="filters.sort" class="form-select">
                                    <option v-for="o in SORT_OPTIONS" :key="o.value" :value="o.value">{{ o.label }}</option>
                                </select>
                            </div>
                            <div class="col-5">
                                <label for="access-filter-order" class="form-label small text-muted mb-1">Order</label>
                                <select id="access-filter-order" v-model="filters.direction" class="form-select">
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
                            <input :id="`access-col-${c.key}`" v-model="visibleColumns" type="checkbox" class="form-check-input" :value="c.key" :disabled="c.locked" />
                            <label :for="`access-col-${c.key}`" class="form-check-label w-100 text-nowrap">{{ c.label }}</label>
                        </div>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Table -->
        <div v-if="error" class="alert alert-danger m-3">{{ error }}</div>
        <LoadingBlock v-else-if="loading && !items.length" />

        <div v-else class="table-responsive access-scroll" :style="{ opacity: loading ? 0.6 : 1 }">
            <table class="table align-middle mb-0 access-table">
                <thead>
                    <tr>
                        <th v-for="c in shownColumns" :key="c.key" scope="col">{{ c.label }}</th>
                        <th scope="col" class="no-print"><span class="visually-hidden">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="!items.length">
                        <td :colspan="shownColumns.length + 1" class="text-center text-muted py-5">No users found.</td>
                    </tr>

                    <tr v-for="(u, index) in items" :key="u.id">
                        <td v-for="col in shownColumns" :key="col.key">
                            <template v-if="col.key === 'id'">{{ rowOffset + index + 1 }}</template>
                            <strong v-else-if="col.key === 'name'" class="access-name">{{ u.name }}</strong>
                            <template v-else-if="col.key === 'email'">{{ u.email }}</template>

                            <span v-else-if="col.key === 'roles'" class="d-inline-flex flex-wrap gap-1">
                                <span v-for="r in u.roles" :key="r.id" class="badge" :class="r.slug === 'super-admin' ? 'text-bg-success' : 'text-bg-primary'">{{ r.name }}</span>
                                <span v-if="!u.roles.length" class="badge text-bg-warning">No role</span>
                            </span>

                            <template v-else-if="col.key === 'created_at'">{{ formatDate(u.created_at) }}</template>
                        </td>

                        <td class="text-end no-print">
                            <button
                                v-if="can('users.edit')"
                                type="button"
                                class="btn btn-sm btn-link text-body row-menu-toggle"
                                aria-haspopup="menu"
                                :aria-expanded="menu.user?.id === u.id"
                                :aria-label="`Actions for ${u.name}`"
                                @click="toggleMenu($event, u)"
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

    <!-- Assign roles drawer -->
    <RecordDrawer :show="drawer.show" :title="drawer.userId ? 'Manage Access' : 'New User'" @close="requestClose">
        <UserRolesForm v-if="drawer.show" ref="formRef" :key="route.fullPath" :user-id="drawer.userId" :roles="roles" :groups="groups" @saved="onSaved" @cancel="closeDrawer" />
    </RecordDrawer>

    <!-- Row actions menu -->
    <Teleport to="body">
        <ul v-if="menu.user" class="dropdown-menu show row-menu shadow" role="menu" :style="{ top: `${menu.top}px`, right: `${menu.right}px` }">
            <li>
                <RouterLink class="dropdown-item" role="menuitem" :to="{ name: 'access.users.edit', params: { id: menu.user.id } }" @click="closeMenu">
                    <i class="bi bi-shield-check me-2"></i>Manage access
                </RouterLink>
            </li>
        </ul>
    </Teleport>

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
.access-page {
    font-family: var(--crm-font);
    font-size: var(--crm-fs);
    line-height: 1.5;
    color: var(--crm-text);
}

.access-title {
    font-size: 1.5rem;
    font-weight: 600;
    letter-spacing: -0.02em;
}

.access-page .form-control,
.access-page .form-select {
    min-height: var(--crm-control-h);
    padding-block: 0.375rem;
    font-size: var(--crm-fs);
}

.access-page .btn:not(.btn-sm) {
    --bs-btn-font-size: var(--crm-fs);
    --bs-btn-font-weight: 500;
    --bs-btn-padding-x: 0.875rem;
    --bs-btn-border-radius: var(--crm-radius);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: var(--crm-control-h);
}

.access-page .nav-link {
    padding: 0.4rem 0.875rem;
    font-size: var(--crm-fs);
    font-weight: 500;
}

.access-page .badge {
    font-size: var(--crm-fs-xs);
    font-weight: 500;
}

.access-page .dropdown-menu,
.row-menu {
    --bs-dropdown-font-size: var(--crm-fs);
    --bs-dropdown-border-radius: var(--crm-radius);
    --bs-dropdown-item-padding-y: 0.4rem;
}

.access-page :deep(.pagination) {
    --bs-pagination-font-size: var(--crm-fs);
}

.access-kpis {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(12rem, 1fr));
    border-bottom: 1px solid var(--crm-border);
}

.access-kpi {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    padding: 1rem 1.25rem;
}

.access-kpi + .access-kpi {
    border-left: 1px solid var(--crm-border);
}

.access-kpi__label {
    font-size: var(--crm-fs-sm);
    font-weight: 500;
    color: var(--crm-text-muted);
}

.access-kpi__value {
    font-size: 1.125rem;
    font-weight: 600;
    font-variant-numeric: tabular-nums;
    line-height: 1.4;
}

.access-kpi__hint {
    font-size: var(--crm-fs-xs);
    color: var(--crm-text-muted);
}

.access-search {
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

.access-table {
    --bs-table-bg: transparent;
    font-size: var(--crm-fs);
    color: var(--crm-text);
    font-variant-numeric: tabular-nums;
}

.access-table thead th {
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

.access-table tbody td {
    padding: 0.75rem 1rem;
    white-space: nowrap;
    border-bottom: 1px solid var(--crm-border);
}

.access-table tbody tr:last-child td {
    border-bottom: 0;
}

.access-name {
    font-weight: 500;
}

.row-menu {
    position: fixed;
    left: auto;
    z-index: 1060;
    min-width: 9rem;
    margin: 0;
    font-family: var(--crm-font);
}

@media (max-width: 575.98px) {
    .access-kpi + .access-kpi {
        border-top: 1px solid var(--crm-border);
        border-left: 0;
    }
}

@media print {
    .no-print {
        display: none !important;
    }

    .access-page {
        border: 0 !important;
        box-shadow: none !important;
    }
}
</style>
