<script setup>
import { ref } from 'vue';
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

const { filters, items, meta, loading, error, load, goToPage, reset } = useListQuery(contactsApi.list, {
    search: '',
    status: '',
    sort_by: 'created_at',
    sort_dir: 'desc',
});

const toDelete = ref(null);
const deleting = ref(false);

async function confirmDelete() {
    deleting.value = true;
    try {
        await contactsApi.remove(toDelete.value.id);
        toast.success('Contact deleted.');
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
        <h1 class="h3 mb-0">Contacts</h1>
        <RouterLink :to="{ name: 'contacts.create' }" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i>New contact
        </RouterLink>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <div class="row g-2 align-items-end">
                <div class="col-md-4">
                    <label class="form-label small text-muted mb-1">Search</label>
                    <input v-model="filters.search" type="text" class="form-control" placeholder="Name, email, company…" />
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted mb-1">Status</label>
                    <select v-model="filters.status" class="form-select">
                        <option value="">All</option>
                        <option v-for="s in CONTACT_STATUSES" :key="s.value" :value="s.value">{{ s.label }}</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted mb-1">Sort by</label>
                    <select v-model="filters.sort_by" class="form-select">
                        <option value="created_at">Created</option>
                        <option value="last_name">Last name</option>
                        <option value="company">Company</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small text-muted mb-1">Order</label>
                    <select v-model="filters.sort_dir" class="form-select">
                        <option value="desc">Newest / Z-A</option>
                        <option value="asc">Oldest / A-Z</option>
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
                        <th>Name</th><th>Email</th><th>Company</th><th>Status</th><th>Created</th><th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="!items.length">
                        <td colspan="6" class="text-center text-muted py-5">No contacts found.</td>
                    </tr>
                    <tr v-for="c in items" :key="c.id">
                        <td>
                            <RouterLink :to="{ name: 'contacts.show', params: { id: c.id } }" class="fw-medium text-decoration-none">{{ c.full_name }}</RouterLink>
                        </td>
                        <td>{{ c.email }}</td>
                        <td>{{ c.company ?? '—' }}</td>
                        <td><StatusBadge kind="contact" :value="c.status" /></td>
                        <td class="text-muted small">{{ formatDate(c.created_at) }}</td>
                        <td class="text-end text-nowrap">
                            <RouterLink :to="{ name: 'contacts.show', params: { id: c.id } }" class="btn btn-sm btn-outline-secondary" title="View"><i class="bi bi-eye"></i></RouterLink>
                            <RouterLink :to="{ name: 'contacts.edit', params: { id: c.id } }" class="btn btn-sm btn-outline-primary ms-1" title="Edit"><i class="bi bi-pencil"></i></RouterLink>
                            <button class="btn btn-sm btn-outline-danger ms-1" title="Delete" @click="toDelete = c"><i class="bi bi-trash"></i></button>
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
        title="Delete contact"
        :message="`Delete ${toDelete?.full_name}? This can't be undone from the panel.`"
        :loading="deleting"
        @confirm="confirmDelete"
        @cancel="toDelete = null"
    />
</template>
