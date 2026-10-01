<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import { contactsApi } from '@/api/contacts';
import { CONTACT_STATUSES } from '@/constants';
import { useToastStore } from '@/stores/toast';
import { parseApiError } from '@/utils/errors';
import FormField from '@/components/FormField.vue';
import LoadingBlock from '@/components/LoadingBlock.vue';

const props = defineProps({ id: { type: String, default: null } });

const router = useRouter();
const toast = useToastStore();

const isEdit = computed(() => Boolean(props.id));
const form = reactive({ first_name: '', last_name: '', email: '', phone: '', company: '', status: 'lead' });
const errors = ref({});
const loading = ref(isEdit.value);
const saving = ref(false);

onMounted(async () => {
    if (!isEdit.value) return;
    try {
        const c = await contactsApi.get(props.id);
        Object.assign(form, {
            first_name: c.first_name,
            last_name: c.last_name,
            email: c.email,
            phone: c.phone ?? '',
            company: c.company ?? '',
            status: c.status,
        });
    } catch {
        toast.error('Contact not found.');
        router.replace({ name: 'contacts.index' });
    } finally {
        loading.value = false;
    }
});

async function submit() {
    saving.value = true;
    errors.value = {};
    try {
        const saved = isEdit.value
            ? await contactsApi.update(props.id, { ...form })
            : await contactsApi.create({ ...form });
        toast.success(isEdit.value ? 'Contact updated.' : 'Contact created.');
        router.push({ name: 'contacts.show', params: { id: saved.id } });
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
    <h1 class="h3 mb-4">{{ isEdit ? 'Edit contact' : 'New contact' }}</h1>

    <LoadingBlock v-if="loading" />

    <div v-else class="card">
        <div class="card-body">
            <form class="row g-3" @submit.prevent="submit">
                <FormField class="col-md-6" label="First name" required :error="errors.first_name">
                    <input v-model="form.first_name" type="text" required class="form-control" :class="{ 'is-invalid': errors.first_name }" />
                </FormField>
                <FormField class="col-md-6" label="Last name" required :error="errors.last_name">
                    <input v-model="form.last_name" type="text" required class="form-control" :class="{ 'is-invalid': errors.last_name }" />
                </FormField>
                <FormField class="col-md-6" label="Email" required :error="errors.email">
                    <input v-model="form.email" type="email" required class="form-control" :class="{ 'is-invalid': errors.email }" />
                </FormField>
                <FormField class="col-md-6" label="Phone" :error="errors.phone">
                    <input v-model="form.phone" type="text" class="form-control" :class="{ 'is-invalid': errors.phone }" />
                </FormField>
                <FormField class="col-md-6" label="Company" :error="errors.company">
                    <input v-model="form.company" type="text" class="form-control" :class="{ 'is-invalid': errors.company }" />
                </FormField>
                <FormField class="col-md-6" label="Status" :error="errors.status">
                    <select v-model="form.status" class="form-select" :class="{ 'is-invalid': errors.status }">
                        <option v-for="s in CONTACT_STATUSES" :key="s.value" :value="s.value">{{ s.label }}</option>
                    </select>
                </FormField>

                <div class="col-12 d-flex gap-2 mt-4">
                    <button class="btn btn-primary" :disabled="saving">
                        <span v-if="saving" class="spinner-border spinner-border-sm me-1"></span>
                        <i v-else class="bi bi-check-lg me-1"></i>Save
                    </button>
                    <RouterLink :to="{ name: 'contacts.index' }" class="btn btn-outline-secondary">Cancel</RouterLink>
                </div>
            </form>
        </div>
    </div>
</template>
