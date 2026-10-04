<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import { leadsApi } from '@/api/leads';
import { usersApi } from '@/api/users';
import { LEAD_SOURCES } from '@/constants';
import { useToastStore } from '@/stores/toast';
import { parseApiError } from '@/utils/errors';
import FormField from '@/components/FormField.vue';
import LoadingBlock from '@/components/LoadingBlock.vue';

const props = defineProps({ id: { type: String, default: null } });

const router = useRouter();
const toast = useToastStore();

const isEdit = computed(() => Boolean(props.id));
const form = reactive({
    first_name: '', last_name: '', email: '', phone: '', company: '', job_title: '',
    source: 'website', score: 0, estimated_value: '', currency: 'USD', assigned_to: '', notes: '',
});
const owners = ref([]);
const errors = ref({});
const loading = ref(true);
const saving = ref(false);

onMounted(async () => {
    try {
        const [users, lead] = await Promise.all([
            usersApi.options(),
            isEdit.value ? leadsApi.get(props.id) : Promise.resolve(null),
        ]);
        owners.value = users;

        if (lead) {
            if (lead.is_converted) {
                toast.error('A converted lead can no longer be edited.');
                return router.replace({ name: 'leads.show', params: { id: lead.id } });
            }
            Object.assign(form, {
                first_name: lead.first_name,
                last_name: lead.last_name,
                email: lead.email,
                phone: lead.phone ?? '',
                company: lead.company ?? '',
                job_title: lead.job_title ?? '',
                source: lead.source,
                score: lead.score,
                estimated_value: lead.estimated_value ?? '',
                currency: lead.currency,
                assigned_to: lead.assigned_to ?? '',
                notes: lead.notes ?? '',
            });
        }
    } catch {
        toast.error('Could not load the lead.');
        router.replace({ name: 'leads.index' });
    } finally {
        loading.value = false;
    }
});

/** Empty inputs become null so optional fields can be cleared on update. */
function payload() {
    const nullable = ['phone', 'company', 'job_title', 'estimated_value', 'assigned_to', 'notes'];
    const body = { ...form, score: Number(form.score) };
    for (const key of nullable) if (body[key] === '') body[key] = null;
    return body;
}

async function submit() {
    saving.value = true;
    errors.value = {};
    try {
        const saved = isEdit.value
            ? await leadsApi.update(props.id, payload())
            : await leadsApi.create(payload());
        toast.success(isEdit.value ? 'Lead updated.' : 'Lead created.');
        router.push({ name: 'leads.show', params: { id: saved.id } });
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
    <h1 class="h3 mb-4">{{ isEdit ? 'Edit lead' : 'New lead' }}</h1>

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
                <FormField class="col-md-6" label="Job title" :error="errors.job_title">
                    <input v-model="form.job_title" type="text" class="form-control" :class="{ 'is-invalid': errors.job_title }" />
                </FormField>

                <FormField class="col-md-4" label="Source" :error="errors.source">
                    <select v-model="form.source" class="form-select" :class="{ 'is-invalid': errors.source }">
                        <option v-for="s in LEAD_SOURCES" :key="s.value" :value="s.value">{{ s.label }}</option>
                    </select>
                </FormField>
                <FormField class="col-md-4" label="Owner" :error="errors.assigned_to">
                    <select v-model="form.assigned_to" class="form-select" :class="{ 'is-invalid': errors.assigned_to }">
                        <option value="">Unassigned</option>
                        <option v-for="o in owners" :key="o.id" :value="o.id">{{ o.name }}</option>
                    </select>
                </FormField>
                <FormField class="col-md-4" :label="`Score: ${form.score}`" :error="errors.score" hint="0 (cold) – 100 (hot)">
                    <input v-model="form.score" type="range" min="0" max="100" step="5" class="form-range" />
                </FormField>

                <FormField class="col-md-4" label="Estimated value" :error="errors.estimated_value">
                    <input v-model="form.estimated_value" type="number" min="0" step="0.01" class="form-control" :class="{ 'is-invalid': errors.estimated_value }" />
                </FormField>
                <FormField class="col-md-2" label="Currency" :error="errors.currency">
                    <input v-model="form.currency" type="text" maxlength="3" class="form-control text-uppercase" :class="{ 'is-invalid': errors.currency }" />
                </FormField>
                <FormField class="col-12" label="Notes" :error="errors.notes">
                    <textarea v-model="form.notes" rows="4" class="form-control" :class="{ 'is-invalid': errors.notes }"></textarea>
                </FormField>

                <div class="col-12 d-flex gap-2 mt-4">
                    <button class="btn btn-primary" :disabled="saving">
                        <span v-if="saving" class="spinner-border spinner-border-sm me-1"></span>
                        <i v-else class="bi bi-check-lg me-1"></i>Save
                    </button>
                    <RouterLink :to="isEdit ? { name: 'leads.show', params: { id } } : { name: 'leads.index' }" class="btn btn-outline-secondary">Cancel</RouterLink>
                </div>
            </form>
        </div>
    </div>
</template>
