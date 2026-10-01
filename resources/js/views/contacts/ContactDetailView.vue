<script setup>
import { ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import { contactsApi } from '@/api/contacts';
import { useToastStore } from '@/stores/toast';
import { parseApiError } from '@/utils/errors';
import { formatDate, money } from '@/utils/format';
import ConfirmModal from '@/components/ConfirmModal.vue';
import LoadingBlock from '@/components/LoadingBlock.vue';
import StatusBadge from '@/components/StatusBadge.vue';

const props = defineProps({ id: { type: String, required: true } });

const router = useRouter();
const toast = useToastStore();

const contact = ref(null);
const confirming = ref(false);
const deleting = ref(false);

watch(
    () => props.id,
    async (id) => {
        contact.value = null;
        try {
            contact.value = await contactsApi.get(id);
        } catch {
            toast.error('Contact not found.');
            router.replace({ name: 'contacts.index' });
        }
    },
    { immediate: true },
);

async function remove() {
    deleting.value = true;
    try {
        await contactsApi.remove(props.id);
        toast.success('Contact deleted.');
        router.push({ name: 'contacts.index' });
    } catch (e) {
        toast.error(parseApiError(e).message);
        confirming.value = false;
    } finally {
        deleting.value = false;
    }
}
</script>

<template>
    <LoadingBlock v-if="!contact" />

    <template v-else>
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 mb-0">{{ contact.full_name }}</h1>
            <div class="d-flex gap-2">
                <RouterLink :to="{ name: 'contacts.edit', params: { id } }" class="btn btn-outline-primary"><i class="bi bi-pencil me-1"></i>Edit</RouterLink>
                <button class="btn btn-outline-danger" @click="confirming = true"><i class="bi bi-trash me-1"></i>Delete</button>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-lg-4">
                <div class="card"><div class="card-body">
                    <StatusBadge kind="contact" :value="contact.status" class="mb-3 d-inline-block" />
                    <dl class="mb-0">
                        <dt class="small text-muted">Email</dt><dd>{{ contact.email }}</dd>
                        <dt class="small text-muted">Phone</dt><dd>{{ contact.phone ?? '—' }}</dd>
                        <dt class="small text-muted">Company</dt><dd>{{ contact.company ?? '—' }}</dd>
                        <dt class="small text-muted">Created</dt><dd class="mb-0">{{ formatDate(contact.created_at, true) }}</dd>
                    </dl>
                </div></div>
            </div>

            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <span class="fw-semibold">Deals ({{ contact.deals.length }})</span>
                        <RouterLink :to="{ name: 'deals.create', query: { contact_id: contact.id } }" class="btn btn-sm btn-primary">
                            <i class="bi bi-plus-lg me-1"></i>New deal
                        </RouterLink>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light"><tr><th>Title</th><th>Amount</th><th>Stage</th></tr></thead>
                            <tbody>
                                <tr v-if="!contact.deals.length">
                                    <td colspan="3" class="text-center text-muted py-4">No deals for this contact.</td>
                                </tr>
                                <tr v-for="d in contact.deals" :key="d.id">
                                    <td><RouterLink :to="{ name: 'deals.show', params: { id: d.id } }" class="text-decoration-none">{{ d.title }}</RouterLink></td>
                                    <td>{{ money(d.amount, d.currency) }}</td>
                                    <td><StatusBadge kind="deal" :value="d.stage" /></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <ConfirmModal
            :show="confirming"
            title="Delete contact"
            :message="`Delete ${contact.full_name}?`"
            :loading="deleting"
            @confirm="remove"
            @cancel="confirming = false"
        />
    </template>
</template>
