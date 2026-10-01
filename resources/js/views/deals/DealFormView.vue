<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { contactsApi } from '@/api/contacts';
import { dealsApi } from '@/api/deals';
import { useToastStore } from '@/stores/toast';
import { parseApiError } from '@/utils/errors';
import FormField from '@/components/FormField.vue';
import LoadingBlock from '@/components/LoadingBlock.vue';

const props = defineProps({ id: { type: String, default: null } });

const route = useRoute();
const router = useRouter();
const toast = useToastStore();

const isEdit = computed(() => Boolean(props.id));
const form = reactive({
    title: '',
    contact_id: route.query.contact_id ? String(route.query.contact_id) : '',
    amount: '',
    currency: 'USD',
    expected_close_date: '',
});
const contactName = ref('');
const contacts = ref([]);
const errors = ref({});
const loading = ref(true);
const saving = ref(false);

onMounted(async () => {
    try {
        if (isEdit.value) {
            const d = await dealsApi.get(props.id);
            if (d.is_closed) {
                toast.error(`A ${d.stage} deal can no longer be edited.`);
                return router.replace({ name: 'deals.show', params: { id: d.id } });
            }
            Object.assign(form, {
                title: d.title,
                amount: d.amount,
                currency: d.currency,
                expected_close_date: d.expected_close_date ?? '',
            });
            contactName.value = d.contact?.full_name ?? '';
        } else {
            contacts.value = await contactsApi.options();
        }
    } catch {
        toast.error('Failed to load the form.');
        return router.replace({ name: 'deals.index' });
    }
    loading.value = false;
});

async function submit() {
    saving.value = true;
    errors.value = {};
    try {
        const payload = {
            title: form.title,
            amount: form.amount,
            currency: form.currency,
            expected_close_date: form.expected_close_date,
        };
        const saved = isEdit.value
            ? await dealsApi.update(props.id, payload)
            : await dealsApi.create({ ...payload, contact_id: form.contact_id });

        toast.success(isEdit.value ? 'Deal updated.' : 'Deal created.');
        router.push({ name: 'deals.show', params: { id: saved.id } });
    } catch (e) {
        const parsed = parseApiError(e);
        errors.value = parsed.fields;
        toast.error(parsed.message);
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <h1 class="h3 mb-4">{{ isEdit ? 'Edit deal' : 'New deal' }}</h1>

    <LoadingBlock v-if="loading" />

    <div v-else class="card">
        <div class="card-body">
            <form class="row g-3" @submit.prevent="submit">
                <FormField class="col-12" label="Title" required :error="errors.title">
                    <input v-model="form.title" type="text" required class="form-control" :class="{ 'is-invalid': errors.title }" />
                </FormField>

                <FormField
                    class="col-12"
                    label="Contact"
                    required
                    :error="errors.contact_id"
                    :hint="isEdit ? 'The contact cannot be changed after creation.' : ''"
                >
                    <input v-if="isEdit" :value="contactName" type="text" class="form-control" disabled />
                    <select v-else v-model="form.contact_id" required class="form-select" :class="{ 'is-invalid': errors.contact_id }">
                        <option value="">Select a contact…</option>
                        <option v-for="c in contacts" :key="c.id" :value="c.id">
                            {{ c.name }}{{ c.company ? ' — ' + c.company : '' }}
                        </option>
                    </select>
                </FormField>

                <FormField class="col-md-4" label="Amount" required :error="errors.amount">
                    <input v-model="form.amount" type="number" step="0.01" min="0" required class="form-control" :class="{ 'is-invalid': errors.amount }" />
                </FormField>
                <FormField class="col-md-4" label="Currency" :error="errors.currency">
                    <input v-model="form.currency" type="text" maxlength="3" class="form-control text-uppercase" :class="{ 'is-invalid': errors.currency }" />
                </FormField>
                <FormField class="col-md-4" label="Expected close date" :error="errors.expected_close_date">
                    <input v-model="form.expected_close_date" type="date" class="form-control" :class="{ 'is-invalid': errors.expected_close_date }" />
                </FormField>

                <div class="col-12 d-flex gap-2 mt-4">
                    <button class="btn btn-primary" :disabled="saving">
                        <span v-if="saving" class="spinner-border spinner-border-sm me-1"></span>
                        <i v-else class="bi bi-check-lg me-1"></i>Save
                    </button>
                    <RouterLink :to="{ name: 'deals.index' }" class="btn btn-outline-secondary">Cancel</RouterLink>
                </div>
            </form>
        </div>
    </div>
</template>
