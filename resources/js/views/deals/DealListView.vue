<script setup>
import { onMounted, ref } from 'vue';
import { contactsApi } from '@/api/contacts';
import { dealsApi } from '@/api/deals';
import { DEAL_STAGES } from '@/constants';
import { useListQuery } from '@/composables/useListQuery';
import { useToastStore } from '@/stores/toast';
import { parseApiError } from '@/utils/errors';
import { formatDate, money } from '@/utils/format';
import ConfirmModal from '@/components/ConfirmModal.vue';
import LoadingBlock from '@/components/LoadingBlock.vue';
import Pagination from '@/components/Pagination.vue';
import StatusBadge from '@/components/StatusBadge.vue';

const toast = useToastStore();

const { filters, items, meta, loading, error, load, goToPage, reset } = useListQuery(dealsApi.list, {
    search: '',
    stage: '',
    contact_id: '',
    sort_by: 'created_at',
    sort_dir: 'desc',
});

const contacts = ref([]);
onMounted(async () => {
    try {
        contacts.value = await contactsApi.options();
    } catch {
        /* the filter just stays empty */
    }
});

const toDelete = ref(null);
const deleting = ref(false);

async function confirmDelete() {
    deleting.value = true;
    try {
        await dealsApi.remove(toDelete.value.id);
        toast.success('Deal deleted.');
        toDelete.value = null;
        await load();
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
        <h1 class="h3 mb-0">Deals</h1>
        <RouterLink :to="{ name: 'deals.create' }" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i>New deal
        </RouterLink>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <div class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small text-muted mb-1">Search</label>
                    <input v-model="filters.search" type="text" class="form-control" placeholder="Deal title…" />
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted mb-1">Stage</label>
                    <select v-model="filters.stage" class="form-select">
                        <option value="">All</option>
                        <option v-for="s in DEAL_STAGES" :key="s.value" :value="s.value">{{ s.label }}</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small text-muted mb-1">Contact</label>
                    <select v-model="filters.contact_id" class="form-select">
                        <option value="">All</option>
                        <option v-for="c in contacts" :key="c.id" :value="c.id">{{ c.name }}</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted mb-1">Sort</label>
                    <select v-model="filters.sort_by" class="form-select">
                        <option value="created_at">Created</option>
                        <option value="amount">Amount</option>
                        <option value="expected_close_date">Close date</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button class="btn btn-outline-secondary w-100" @click="reset"><i class="bi bi-x-lg me-1"></i>Reset</button>
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
                        <th>Title</th><th>Contact</th><th>Amount</th><th>Stage</th><th>Expected close</th><th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="!items.length">
                        <td colspan="6" class="text-center text-muted py-5">No deals found.</td>
                    </tr>
                    <tr v-for="d in items" :key="d.id">
                        <td><RouterLink :to="{ name: 'deals.show', params: { id: d.id } }" class="fw-medium text-decoration-none">{{ d.title }}</RouterLink></td>
                        <td>{{ d.contact?.full_name }}</td>
                        <td>{{ money(d.amount, d.currency) }}</td>
                        <td><StatusBadge kind="deal" :value="d.stage" /></td>
                        <td class="text-muted small">{{ formatDate(d.expected_close_date) }}</td>
                        <td class="text-end text-nowrap">
                            <RouterLink :to="{ name: 'deals.show', params: { id: d.id } }" class="btn btn-sm btn-outline-secondary" title="View"><i class="bi bi-eye"></i></RouterLink>
                            <RouterLink v-if="!d.is_closed" :to="{ name: 'deals.edit', params: { id: d.id } }" class="btn btn-sm btn-outline-primary ms-1" title="Edit"><i class="bi bi-pencil"></i></RouterLink>
                            <button v-if="d.stage !== 'won'" class="btn btn-sm btn-outline-danger ms-1" title="Delete" @click="toDelete = d"><i class="bi bi-trash"></i></button>
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
        title="Delete deal"
        :message="`Delete “${toDelete?.title}”?`"
        :loading="deleting"
        @confirm="confirmDelete"
        @cancel="toDelete = null"
    />
</template>
