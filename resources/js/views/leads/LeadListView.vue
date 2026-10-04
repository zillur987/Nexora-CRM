<script setup>
import { onMounted, ref } from 'vue';
import { leadsApi } from '@/api/leads';
import { usersApi } from '@/api/users';
import { LEAD_SOURCES, LEAD_STATUSES, leadSourceMeta } from '@/constants';
import { useListQuery } from '@/composables/useListQuery';
import { useToastStore } from '@/stores/toast';
import { parseApiError } from '@/utils/errors';
import { formatDate, money } from '@/utils/format';
import ConfirmModal from '@/components/ConfirmModal.vue';
import LoadingBlock from '@/components/LoadingBlock.vue';
import Pagination from '@/components/Pagination.vue';
import ScoreBar from '@/components/ScoreBar.vue';
import StatusBadge from '@/components/StatusBadge.vue';

const toast = useToastStore();

const { filters, items, meta, loading, error, load, goToPage, reset } = useListQuery(leadsApi.list, {
    search: '',
    status: '',
    source: '',
    assigned_to: '',
    sort_by: 'created_at',
    sort_dir: 'desc',
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

onMounted(async () => {
    loadSummary();
    try {
        owners.value = await usersApi.options();
    } catch {
        /* owner filter stays empty */
    }
});

const toDelete = ref(null);
const deleting = ref(false);

async function confirmDelete() {
    deleting.value = true;
    try {
        await leadsApi.remove(toDelete.value.id);
        toast.success('Lead deleted.');
        toDelete.value = null;
        await Promise.all([load(), loadSummary()]);
    } catch (e) {
        toast.error(parseApiError(e).message);
        toDelete.value = null;
    } finally {
        deleting.value = false;
    }
}
</script>

<template>
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Leads</h1>
        <RouterLink :to="{ name: 'leads.create' }" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i>New lead
        </RouterLink>
    </div>

    <ul class="nav nav-pills mb-3 gap-1">
        <li class="nav-item">
            <button class="nav-link" :class="{ active: filters.status === '' }" @click="filters.status = ''">All</button>
        </li>
        <li v-for="s in LEAD_STATUSES" :key="s.value" class="nav-item">
            <button class="nav-link" :class="{ active: filters.status === s.value }" @click="filters.status = s.value">
                {{ s.label }}
                <span v-if="summary[s.value] !== undefined" class="badge rounded-pill ms-1" :class="filters.status === s.value ? 'text-bg-light' : 'text-bg-secondary'">
                    {{ summary[s.value] }}
                </span>
            </button>
        </li>
    </ul>

    <div class="card mb-3">
        <div class="card-body">
            <div class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small text-muted mb-1">Search</label>
                    <input v-model="filters.search" type="text" class="form-control" placeholder="Name, email, company…" />
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted mb-1">Source</label>
                    <select v-model="filters.source" class="form-select">
                        <option value="">All</option>
                        <option v-for="s in LEAD_SOURCES" :key="s.value" :value="s.value">{{ s.label }}</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted mb-1">Owner</label>
                    <select v-model="filters.assigned_to" class="form-select">
                        <option value="">Anyone</option>
                        <option value="unassigned">Unassigned</option>
                        <option v-for="o in owners" :key="o.id" :value="String(o.id)">{{ o.name }}</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted mb-1">Sort by</label>
                    <select v-model="filters.sort_by" class="form-select">
                        <option value="created_at">Created</option>
                        <option value="score">Score</option>
                        <option value="estimated_value">Value</option>
                        <option value="last_contacted_at">Last contacted</option>
                        <option value="last_name">Last name</option>
                        <option value="company">Company</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted mb-1">Order</label>
                    <select v-model="filters.sort_dir" class="form-select">
                        <option value="desc">Descending</option>
                        <option value="asc">Ascending</option>
                    </select>
                </div>
                <div class="col-md-1">
                    <button class="btn btn-outline-secondary w-100" title="Reset filters" @click="reset"><i class="bi bi-x-lg"></i></button>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div v-if="error" class="alert alert-danger m-3">{{ error }}</div>
        <LoadingBlock v-else-if="loading && !items.length" />

        <div v-else class="table-responsive" :style="{ opacity: loading ? 0.6 : 1 }">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Name</th><th>Company</th><th>Source</th><th>Score</th><th>Value</th><th>Owner</th><th>Status</th><th>Created</th><th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="!items.length">
                        <td colspan="9" class="text-center text-muted py-5">No leads found.</td>
                    </tr>
                    <tr v-for="l in items" :key="l.id">
                        <td>
                            <RouterLink :to="{ name: 'leads.show', params: { id: l.id } }" class="fw-medium text-decoration-none">{{ l.full_name }}</RouterLink>
                            <div class="small text-muted">{{ l.email }}</div>
                        </td>
                        <td>{{ l.company ?? '—' }}</td>
                        <td>{{ leadSourceMeta[l.source]?.label ?? l.source }}</td>
                        <td><ScoreBar :value="l.score" /></td>
                        <td>{{ l.estimated_value ? money(l.estimated_value, l.currency) : '—' }}</td>
                        <td>{{ l.owner?.name ?? '—' }}</td>
                        <td><StatusBadge kind="lead" :value="l.status" /></td>
                        <td class="text-muted small">{{ formatDate(l.created_at) }}</td>
                        <td class="text-end text-nowrap">
                            <RouterLink :to="{ name: 'leads.show', params: { id: l.id } }" class="btn btn-sm btn-outline-secondary" title="View"><i class="bi bi-eye"></i></RouterLink>
                            <RouterLink v-if="!l.is_converted" :to="{ name: 'leads.edit', params: { id: l.id } }" class="btn btn-sm btn-outline-primary ms-1" title="Edit"><i class="bi bi-pencil"></i></RouterLink>
                            <button v-if="!l.is_converted" class="btn btn-sm btn-outline-danger ms-1" title="Delete" @click="toDelete = l"><i class="bi bi-trash"></i></button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="meta" class="card-footer bg-white">
            <Pagination :meta="meta" @change="goToPage" />
        </div>
    </div>

    <ConfirmModal
        :show="!!toDelete"
        title="Delete lead"
        :message="`Delete ${toDelete?.full_name}?`"
        :loading="deleting"
        @confirm="confirmDelete"
        @cancel="toDelete = null"
    />
</template>
