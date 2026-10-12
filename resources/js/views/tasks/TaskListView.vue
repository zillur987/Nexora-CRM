<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref, unref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { tasksApi } from '@/api/tasks';
import { useColumnVisibility } from '@/composables/useColumnVisibility';
import { useListQuery } from '@/composables/useListQuery';
import { usePermission } from '@/composables/usePermission';
import { useToastStore } from '@/stores/toast';
import { formatDate } from '@/utils/format';
import { parseApiError } from '@/utils/errors';
import { badgeStyle, SCOPES } from '@/utils/taskOptions';
import ConfirmModal from '@/components/ConfirmModal.vue';
import DeleteConfirmModal from '@/components/DeleteConfirmModal.vue';
import LoadingBlock from '@/components/LoadingBlock.vue';
import Pagination from '@/components/Pagination.vue';
import RecordDrawer from '@/components/RecordDrawer.vue';
import TaskFormView from '@/views/tasks/TaskFormView.vue';

const toast = useToastStore();
const route = useRoute();
const router = useRouter();
const { can } = usePermission();

/* -------------------------------------------------------------------------- */
/* Static config                                                              */
/* -------------------------------------------------------------------------- */

const DEFAULT_FILTERS = {
    search: '',
    scope: '',
    assignee_id: '',
    task_status_id: '',
    task_priority_id: '',
    task_type_id: '',
    company_id: '',
    sort: 'created_at',
    direction: 'desc',
};

const SORT_OPTIONS = [
    { value: 'created_at', label: 'Created' },
    { value: 'due_date', label: 'Due date' },
    { value: 'name', label: 'Task name' },
    { value: 'updated_at', label: 'Last updated' },
];

// Order here = order of table columns and of the Columns dropdown.
const COLUMNS = [
    { key: 'id', label: 'Id', default: true },
    { key: 'name', label: 'Name', locked: true, default: true },
    { key: 'priority', label: 'Priority', default: true },
    { key: 'status', label: 'Status', default: true },
    { key: 'type', label: 'Type', default: true },
    { key: 'assignee', label: 'Assignee', default: true },
    { key: 'due_date', label: 'Due date', default: true },
    { key: 'company', label: 'Company', default: true },
    { key: 'contact', label: 'Contact', default: false },
    { key: 'opportunity', label: 'Opportunity', default: false },
    { key: 'quote', label: 'Quote', default: false },
    { key: 'description', label: 'Description', default: false },
    { key: 'created_at', label: 'Created', default: false },
];

const { visible: visibleColumns, shown: shownColumns } = useColumnVisibility('tasks.visibleColumns.v1', COLUMNS);

/* -------------------------------------------------------------------------- */
/* Data                                                                       */
/* -------------------------------------------------------------------------- */

const { filters, items, meta, loading, error, load, goToPage, reset } = useListQuery(tasksApi.list, { ...DEFAULT_FILTERS });

const summary = ref({ total: 0, open: 0, overdue: 0, due_today: 0, completed: 0 });
const lookups = ref({ users: [], companies: [], types: [], priorities: [], statuses: [] });

async function loadSummary() {
    try {
        summary.value = await tasksApi.summary();
    } catch {
        /* tabs and KPIs just show no figures; the list itself reports its own errors */
    }
}

async function loadLookups() {
    try {
        lookups.value = await tasksApi.lookups();
    } catch {
        /* filter dropdowns stay empty */
    }
}

const refresh = () => Promise.all([load(), loadSummary(), loadLookups()]);

const rowOffset = computed(() => {
    const m = unref(meta);
    if (!m) return 0;
    if (Number.isInteger(m.from)) return m.from - 1;
    return ((m.current_page ?? 1) - 1) * (m.per_page ?? 0);
});

const kpis = computed(() => {
    const s = summary.value;
    return [
        { key: 'open', label: 'Open tasks', value: s.open ?? 0, hint: `${s.total ?? 0} in total` },
        { key: 'today', label: 'Due today', value: s.due_today ?? 0, hint: 'Still to do' },
        { key: 'overdue', label: 'Overdue', value: s.overdue ?? 0, hint: 'Past their due date' },
    ];
});

const TABS = computed(() =>
    SCOPES.map((t) => ({
        ...t,
        count: { '': summary.value.total, open: summary.value.open, today: summary.value.due_today, overdue: summary.value.overdue, completed: summary.value.completed }[t.value] ?? 0,
    })),
);

/* -------------------------------------------------------------------------- */
/* Filter panel + columns dropdown                                            */
/* -------------------------------------------------------------------------- */

const filterOpen = ref(false);
const filterRef = ref(null);
const columnsOpen = ref(false);
const columnsRef = ref(null);

const activeFilterCount = computed(() => {
    const f = unref(filters);
    let count = 0;
    for (const key of ['assignee_id', 'task_status_id', 'task_priority_id', 'task_type_id', 'company_id']) if (f[key] !== '') count++;
    if (f.sort !== DEFAULT_FILTERS.sort || f.direction !== DEFAULT_FILTERS.direction) count++;
    return count;
});

/* -------------------------------------------------------------------------- */
/* Row actions menu (teleported so the scrolling table can't clip it)         */
/* -------------------------------------------------------------------------- */

const menu = ref({ task: null, top: 0, right: 0 });

function closeMenu() {
    menu.value = { task: null, top: 0, right: 0 };
}

function toggleMenu(event, task) {
    if (menu.value.task?.id === task.id) return closeMenu();

    const rect = event.currentTarget.getBoundingClientRect();
    menu.value = { task, top: rect.bottom + 4, right: window.innerWidth - rect.right };
}

/* -------------------------------------------------------------------------- */
/* Delete                                                                     */
/* -------------------------------------------------------------------------- */

const toDelete = ref(null);
const deleting = ref(false);

function askDelete(task) {
    closeMenu();
    toDelete.value = task;
}

async function confirmDelete() {
    deleting.value = true;
    try {
        const wasLastRowOnPage = items.value.length === 1;
        const page = unref(meta)?.current_page ?? 1;

        await tasksApi.remove(toDelete.value.id);
        toast.success('Task deleted.');
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

const drawer = reactive({ show: false, taskId: null, readonly: false });
const formRef = ref(null);
const confirmDiscard = ref(false);

watch(
    () => [route.name, route.params.id],
    ([name, id]) => {
        drawer.show = ['tasks.create', 'tasks.edit', 'tasks.show'].includes(name);
        if (!drawer.show) return;
        drawer.taskId = name === 'tasks.create' ? null : String(id);
        drawer.readonly = name === 'tasks.show';
    },
    { immediate: true },
);

const drawerTitle = computed(() => (drawer.taskId === null ? 'Create Task' : drawer.readonly ? 'Task details' : 'Edit Task'));

function closeDrawer() {
    confirmDiscard.value = false;
    router.replace({ name: 'tasks.index', query: route.query });
}

function requestClose() {
    if (formRef.value?.isDirty) {
        confirmDiscard.value = true;
        return;
    }
    closeDrawer();
}

async function onTaskSaved() {
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
    if (menu.value.task && !target.closest?.('.row-menu, .row-menu-toggle')) closeMenu();
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
    <div class="card tasks-page rounded-3 overflow-visible">
        <!-- Header -->
        <div class="card-header bg-white d-flex justify-content-between align-items-center px-3 py-3 rounded-top-3">
            <h1 class="tasks-title mb-0">Tasks</h1>
            <RouterLink v-can="'tasks.create'" :to="{ name: 'tasks.create' }" class="btn btn-primary no-print">
                <i class="bi bi-plus-lg me-2"></i>Create Task
            </RouterLink>
        </div>

        <!-- KPIs -->
        <section class="tasks-kpis" aria-label="Task summary">
            <div v-for="k in kpis" :key="k.key" class="tasks-kpi">
                <span class="tasks-kpi__label">{{ k.label }}</span>
                <strong class="tasks-kpi__value" :class="{ 'text-danger': k.key === 'overdue' && k.value > 0 }">{{ k.value }}</strong>
                <span class="tasks-kpi__hint">{{ k.hint }}</span>
            </div>
        </section>

        <!-- Scope tabs -->
        <ul class="nav nav-pills flex-nowrap overflow-auto gap-1 px-3 pt-3 pb-3 no-print">
            <li v-for="t in TABS" :key="t.value" class="nav-item">
                <button type="button" class="nav-link text-nowrap" :class="{ active: filters.scope === t.value }" @click="filters.scope = t.value">
                    {{ t.label }}
                    <span class="badge rounded-pill ms-1" :class="filters.scope === t.value ? 'text-bg-light' : 'text-bg-secondary'">{{ t.count }}</span>
                </button>
            </li>
        </ul>

        <!-- Toolbar -->
        <div class="tasks-toolbar d-flex flex-wrap align-items-center gap-2 px-3 py-3 bg-light border-top border-bottom no-print">
            <form class="input-group tasks-search" role="search" @submit.prevent="load">
                <input v-model.trim="filters.search" type="search" class="form-control" placeholder="Search" aria-label="Search tasks" />
                <button type="submit" class="btn btn-primary" aria-label="Search"><i class="bi bi-search"></i></button>
            </form>

            <!-- Filter -->
            <div ref="filterRef" class="position-relative">
                <button type="button" class="btn btn-outline-secondary bg-white text-body" aria-haspopup="dialog" :aria-expanded="filterOpen" @click="filterOpen = !filterOpen">
                    <i class="bi bi-funnel me-2"></i>Filter
                    <span v-if="activeFilterCount" class="badge text-bg-primary ms-2">{{ activeFilterCount }}</span>
                    <i class="bi bi-plus-lg ms-3"></i>
                </button>

                <div v-if="filterOpen" class="card shadow filter-panel" role="dialog" aria-label="Filter tasks">
                    <div class="card-body d-flex flex-column gap-3">
                        <div class="row g-2">
                            <div class="col-6">
                                <label for="task-filter-assignee" class="form-label small text-muted mb-1">Assignee</label>
                                <select id="task-filter-assignee" v-model="filters.assignee_id" class="form-select">
                                    <option value="">Any</option>
                                    <option v-for="u in lookups.users" :key="u.id" :value="String(u.id)">{{ u.name }}</option>
                                </select>
                            </div>
                            <div class="col-6">
                                <label for="task-filter-company" class="form-label small text-muted mb-1">Company</label>
                                <select id="task-filter-company" v-model="filters.company_id" class="form-select">
                                    <option value="">Any</option>
                                    <option v-for="c in lookups.companies" :key="c.id" :value="String(c.id)">{{ c.name }}</option>
                                </select>
                            </div>
                            <div class="col-6">
                                <label for="task-filter-status" class="form-label small text-muted mb-1">Status</label>
                                <select id="task-filter-status" v-model="filters.task_status_id" class="form-select">
                                    <option value="">Any</option>
                                    <option v-for="s in lookups.statuses" :key="s.id" :value="String(s.id)">{{ s.name }}</option>
                                </select>
                            </div>
                            <div class="col-6">
                                <label for="task-filter-priority" class="form-label small text-muted mb-1">Priority</label>
                                <select id="task-filter-priority" v-model="filters.task_priority_id" class="form-select">
                                    <option value="">Any</option>
                                    <option v-for="p in lookups.priorities" :key="p.id" :value="String(p.id)">{{ p.name }}</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label for="task-filter-type" class="form-label small text-muted mb-1">Type</label>
                                <select id="task-filter-type" v-model="filters.task_type_id" class="form-select">
                                    <option value="">Any</option>
                                    <option v-for="t in lookups.types" :key="t.id" :value="String(t.id)">{{ t.name }}</option>
                                </select>
                            </div>
                            <div class="col-7">
                                <label for="task-filter-sort" class="form-label small text-muted mb-1">Sort by</label>
                                <select id="task-filter-sort" v-model="filters.sort" class="form-select">
                                    <option v-for="o in SORT_OPTIONS" :key="o.value" :value="o.value">{{ o.label }}</option>
                                </select>
                            </div>
                            <div class="col-5">
                                <label for="task-filter-order" class="form-label small text-muted mb-1">Order</label>
                                <select id="task-filter-order" v-model="filters.direction" class="form-select">
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
                            <input :id="`task-col-${c.key}`" v-model="visibleColumns" type="checkbox" class="form-check-input" :value="c.key" :disabled="c.locked" />
                            <label :for="`task-col-${c.key}`" class="form-check-label w-100 text-nowrap">{{ c.label }}</label>
                        </div>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Table -->
        <div v-if="error" class="alert alert-danger m-3">{{ error }}</div>
        <LoadingBlock v-else-if="loading && !items.length" />

        <div v-else class="table-responsive tasks-scroll" :style="{ opacity: loading ? 0.6 : 1 }">
            <table class="table align-middle mb-0 tasks-table">
                <thead>
                    <tr>
                        <th v-for="c in shownColumns" :key="c.key" scope="col">{{ c.label }}</th>
                        <th scope="col" class="no-print"><span class="visually-hidden">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="!items.length">
                        <td :colspan="shownColumns.length + 1" class="text-center text-muted py-5">No tasks found.</td>
                    </tr>

                    <tr v-for="(t, index) in items" :key="t.id">
                        <td v-for="col in shownColumns" :key="col.key">
                            <template v-if="col.key === 'id'">{{ rowOffset + index + 1 }}</template>

                            <RouterLink v-else-if="col.key === 'name'" :to="{ name: 'tasks.show', params: { id: t.id } }" class="task-link">{{ t.name }}</RouterLink>

                            <span v-else-if="col.key === 'priority'">
                                <span v-if="t.priority" class="task-pill" :style="badgeStyle(t.priority.color)">{{ t.priority.name }}</span>
                                <template v-else>—</template>
                            </span>

                            <span v-else-if="col.key === 'status'">
                                <span v-if="t.status" class="task-pill" :style="badgeStyle(t.status.color)">{{ t.status.name }}</span>
                                <template v-else>—</template>
                            </span>

                            <template v-else-if="col.key === 'type'">{{ t.type?.name ?? '—' }}</template>
                            <template v-else-if="col.key === 'assignee'">{{ t.assignee?.name ?? '—' }}</template>

                            <span v-else-if="col.key === 'due_date'" :class="{ 'task-overdue': t.is_overdue }" :title="t.is_overdue ? 'Overdue' : ''">
                                {{ t.due_date ? formatDate(t.due_date) : '—' }}
                            </span>

                            <template v-else-if="col.key === 'company'">{{ t.company?.name ?? '—' }}</template>
                            <template v-else-if="col.key === 'contact'">{{ t.contact?.name ?? '—' }}</template>
                            <template v-else-if="col.key === 'opportunity'">{{ t.opportunity?.name ?? '—' }}</template>
                            <template v-else-if="col.key === 'quote'">{{ t.quote?.name ?? '—' }}</template>

                            <span v-else-if="col.key === 'description'" class="task-desc" :title="t.description">{{ t.description || '—' }}</span>

                            <template v-else-if="col.key === 'created_at'">{{ formatDate(t.created_at) }}</template>
                        </td>

                        <td class="text-end no-print">
                            <button
                                type="button"
                                class="btn btn-sm btn-link text-body row-menu-toggle"
                                aria-haspopup="menu"
                                :aria-expanded="menu.task?.id === t.id"
                                :aria-label="`Actions for ${t.name}`"
                                @click="toggleMenu($event, t)"
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
        <TaskFormView ref="formRef" :key="route.fullPath" :task-id="drawer.taskId" :readonly="drawer.readonly" @saved="onTaskSaved" @cancel="closeDrawer" />
    </RecordDrawer>

    <!-- Row actions menu -->
    <Teleport to="body">
        <ul v-if="menu.task" class="dropdown-menu show row-menu shadow" role="menu" :style="{ top: `${menu.top}px`, right: `${menu.right}px` }">
            <li>
                <RouterLink class="dropdown-item" role="menuitem" :to="{ name: 'tasks.show', params: { id: menu.task.id } }" @click="closeMenu">
                    <i class="bi bi-eye me-2"></i>View
                </RouterLink>
            </li>
            <li v-if="can('tasks.edit')">
                <RouterLink class="dropdown-item" role="menuitem" :to="{ name: 'tasks.edit', params: { id: menu.task.id } }" @click="closeMenu">
                    <i class="bi bi-pencil me-2"></i>Edit
                </RouterLink>
            </li>
            <template v-if="can('tasks.delete')">
                <li><hr class="dropdown-divider" /></li>
                <li>
                    <button type="button" class="dropdown-item text-danger" role="menuitem" @click="askDelete(menu.task)">
                        <i class="bi bi-trash me-2"></i>Delete
                    </button>
                </li>
            </template>
        </ul>
    </Teleport>

    <DeleteConfirmModal :show="!!toDelete" title="Delete task" :message="`Delete ${toDelete?.name}? This cannot be undone.`" :loading="deleting" @confirm="confirmDelete" @cancel="toDelete = null" />

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
.tasks-page {
    font-family: var(--crm-font);
    font-size: var(--crm-fs);
    line-height: 1.5;
    color: var(--crm-text);
}

.tasks-title {
    font-size: 1.5rem;
    font-weight: 600;
    letter-spacing: -0.02em;
}

.tasks-page .form-control,
.tasks-page .form-select {
    min-height: var(--crm-control-h);
    padding-block: 0.375rem;
    font-size: var(--crm-fs);
}

.tasks-page .btn:not(.btn-sm) {
    --bs-btn-font-size: var(--crm-fs);
    --bs-btn-font-weight: 500;
    --bs-btn-padding-x: 0.875rem;
    --bs-btn-border-radius: var(--crm-radius);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: var(--crm-control-h);
}

.tasks-page .nav-link {
    padding: 0.4rem 0.875rem;
    font-size: var(--crm-fs);
    font-weight: 500;
}

.tasks-page .badge {
    font-size: var(--crm-fs-xs);
    font-weight: 500;
}

.tasks-page .dropdown-menu,
.row-menu {
    --bs-dropdown-font-size: var(--crm-fs);
    --bs-dropdown-border-radius: var(--crm-radius);
    --bs-dropdown-item-padding-y: 0.4rem;
}

.tasks-page :deep(.pagination) {
    --bs-pagination-font-size: var(--crm-fs);
}

/* KPIs */
.tasks-kpis {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(12rem, 1fr));
    border-bottom: 1px solid var(--crm-border);
}

.tasks-kpi {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    padding: 1rem 1.25rem;
}

.tasks-kpi + .tasks-kpi {
    border-left: 1px solid var(--crm-border);
}

.tasks-kpi__label {
    font-size: var(--crm-fs-sm);
    font-weight: 500;
    color: var(--crm-text-muted);
}

.tasks-kpi__value {
    font-size: 1.125rem;
    font-weight: 600;
    font-variant-numeric: tabular-nums;
    line-height: 1.4;
}

.tasks-kpi__hint {
    font-size: var(--crm-fs-xs);
    color: var(--crm-text-muted);
}

/* Toolbar */
.tasks-search {
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
.tasks-table {
    --bs-table-bg: transparent;
    font-size: var(--crm-fs);
    color: var(--crm-text);
    font-variant-numeric: tabular-nums;
}

.tasks-table thead th {
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

.tasks-table tbody td {
    padding: 0.75rem 1rem;
    white-space: nowrap;
    border-bottom: 1px solid var(--crm-border);
}

.tasks-table tbody tr:last-child td {
    border-bottom: 0;
}

.task-desc {
    display: inline-block;
    max-width: 28rem;
    overflow: hidden;
    color: var(--crm-text-muted);
    text-overflow: ellipsis;
    vertical-align: bottom;
}

.task-link {
    color: inherit;
    text-decoration: none;
}

.task-link:hover {
    color: var(--bs-primary);
    text-decoration: underline;
}

.task-desc {
    max-width: 22rem;
}

.task-overdue {
    font-weight: 500;
    color: var(--bs-danger);
}

.task-pill {
    display: inline-block;
    padding: 0.15rem 0.55rem;
    font-size: var(--crm-fs-xs);
    font-weight: 500;
    border-radius: 999px;
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
    .tasks-kpi + .tasks-kpi {
        border-top: 1px solid var(--crm-border);
        border-left: 0;
    }
}

@media print {
    .no-print {
        display: none !important;
    }

    .tasks-page {
        border: 0 !important;
        box-shadow: none !important;
    }
}
</style>
