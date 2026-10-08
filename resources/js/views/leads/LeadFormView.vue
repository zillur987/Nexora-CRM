<script setup>
import { computed, nextTick, onMounted, reactive, ref } from 'vue';
import { leadsApi } from '@/api/leads';
import { LEAD_SOURCES } from '@/constants';
import { useToastStore } from '@/stores/toast';
import { parseApiError } from '@/utils/errors';
import FormField from '@/components/FormField.vue';
import LoadingBlock from '@/components/LoadingBlock.vue';
import { downloadCsv } from '@/utils/csv';

const props = defineProps({
    leadId: { type: [String, Number], default: null },
    owners: { type: Array, default: () => [] },
});
const emit = defineEmits(['saved', 'cancel', 'imported']);

const toast = useToastStore();

/* -------------------------------------------------------------------------- */
/* Form model                                                                 */
/* -------------------------------------------------------------------------- */

const CURRENCIES = ['USD', 'EUR', 'GBP', 'BDT', 'INR', 'AED'];
const NULLABLE_FIELDS = ['phone', 'company', 'job_title', 'estimated_value', 'assigned_to', 'notes'];

const blank = () => ({
    first_name: '',
    last_name: '',
    email: '',
    phone: '',
    company: '',
    job_title: '',
    source: 'website',
    score: 0,
    estimated_value: '',
    currency: 'USD',
    assigned_to: '',
    notes: '',
});

const fromLead = (lead) => ({
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

/** Empty inputs become null so optional fields can be cleared on update. */
function toPayload(values) {
    const body = { ...values, score: Number(values.score) };
    for (const key of NULLABLE_FIELDS) if (body[key] === '') body[key] = null;
    return body;
}

const isEdit = computed(() => props.leadId !== null);
const form = reactive(blank());
const errors = ref({});
const loading = ref(isEdit.value);
const saving = ref(false);
const formEl = ref(null);

// Unsaved-changes tracking: the drawer asks before discarding a dirty form.
const serialize = () => JSON.stringify(form);
const snapshot = ref(serialize());
const isDirty = computed(() => serialize() !== snapshot.value);
defineExpose({ isDirty });

const currencyOptions = computed(() => (CURRENCIES.includes(form.currency) ? CURRENCIES : [form.currency, ...CURRENCIES]));

const scoreLevel = computed(() => {
    if (form.score < 34) return { key: 'cold', label: 'Cold' };
    if (form.score < 67) return { key: 'warm', label: 'Warm' };
    return { key: 'hot', label: 'Hot' };
});

const cls = (field) => ({ 'is-invalid': Boolean(errors.value[field]) });

/* -------------------------------------------------------------------------- */
/* Load (edit mode)                                                           */
/* -------------------------------------------------------------------------- */

onMounted(async () => {
    if (!isEdit.value) return;

    try {
        const lead = await leadsApi.get(props.leadId);
        if (lead.is_converted) {
            toast.error('A converted lead can no longer be edited.');
            return emit('cancel');
        }
        Object.assign(form, fromLead(lead));
        snapshot.value = serialize();
    } catch {
        toast.error('Could not load the lead.');
        emit('cancel');
    } finally {
        loading.value = false;
    }
});

/* -------------------------------------------------------------------------- */
/* Submit                                                                     */
/* -------------------------------------------------------------------------- */

const focusFirstField = () => formEl.value?.querySelector('input:not([type="hidden"])')?.focus();
const focusFirstInvalid = () => formEl.value?.querySelector('.is-invalid')?.focus();

async function submit(addAnother) {
    saving.value = true;
    errors.value = {};

    try {
        const body = toPayload(form);
        const saved = isEdit.value ? await leadsApi.update(props.leadId, body) : await leadsApi.create(body);
        toast.success(isEdit.value ? 'Lead updated.' : 'Lead created.');

        const keepOpen = addAnother && !isEdit.value;
        if (keepOpen) {
            // Keep source, owner and currency: they rarely change between consecutive entries.
            const { source, assigned_to, currency } = form;
            Object.assign(form, blank(), { source, assigned_to, currency });
        }
        snapshot.value = serialize();

        emit('saved', saved, { another: keepOpen });
        if (keepOpen) await nextTick(focusFirstField);
    } catch (e) {
        const parsed = parseApiError(e);
        errors.value = parsed.fields;
        toast.error(parsed.message);
        await nextTick(focusFirstInvalid);
    } finally {
        saving.value = false;
    }
}

const onSubmit = (event) => submit(event.submitter?.dataset.action === 'another');

const csvFile = ref(null);
const csvInput = ref(null);
const csvError = ref('');
const csvImporting = ref(false);

const CSV_HEADERS = [
    'first_name', 'last_name', 'email', 'phone', 'company',
    'job_title', 'source', 'score', 'estimated_value', 'currency', 'notes',
];

function handleCsvFile(event) {
    const file = event.target.files?.[0];

    csvError.value = '';

    if (!file) {
        csvFile.value = null;
        return;
    }

    if (!file.name.toLowerCase().endsWith('.csv')) {
        csvError.value = 'Please select a CSV file for uploading';

        event.target.value = '';
        csvFile.value = null;

        return;
    }

    csvFile.value = file;
}

function downloadDemoCsv() {
    downloadCsv('leads-demo.csv', CSV_HEADERS, [
        ['Jane', 'Doe', 'jane@company.com', '+15550100', 'Acme Inc.', 'Head of Sales', 'website', 50, 1500, 'USD', 'Met at expo'],
        ['John', 'Smith', 'john@example.com', '+15550101', 'Globex', 'CTO', 'referral', 80, 5000, 'USD', ''],
    ]);
}

async function importCsv() {
    if (!csvFile.value) {
        csvError.value = 'Please select a CSV file for uploading';
        return;
    }

    csvImporting.value = true;
    csvError.value = '';

    try {
        const result = await leadsApi.import(csvFile.value);
        toast.success(result?.message ?? 'Leads imported.');

        csvFile.value = null;
        if (csvInput.value) csvInput.value.value = '';

        emit('imported', result);

    } catch (error) {
        csvError.value =
            error?.response?.data?.message ||
            'Unable to import CSV file.';
    } finally {
        csvImporting.value = false;
    }
}
</script>

<template>
    <LoadingBlock v-if="loading" />

    <form v-else ref="formEl" class="lead-form" @submit.prevent="onSubmit">
        <section class="lead-form__section">
            <h3 class="lead-form__title"><i class="bi bi-person"></i>Contact</h3>
            <div class="row g-3">
                <FormField class="col-md-6" label="First name" required :error="errors.first_name">
                    <input v-model="form.first_name" type="text" required placeholder="Jane" class="form-control" :class="cls('first_name')" />
                </FormField>
                <FormField class="col-md-6" label="Last name" required :error="errors.last_name">
                    <input v-model="form.last_name" type="text" required placeholder="Doe" class="form-control" :class="cls('last_name')" />
                </FormField>
                <FormField class="col-md-6" label="Email" required :error="errors.email">
                    <input v-model="form.email" type="email" required placeholder="jane@company.com" class="form-control" :class="cls('email')" />
                </FormField>
                <FormField class="col-md-6" label="Phone" :error="errors.phone">
                    <input v-model="form.phone" type="tel" placeholder="+1 555 0100" class="form-control" :class="cls('phone')" />
                </FormField>
            </div>
        </section>

        <section class="lead-form__section">
            <h3 class="lead-form__title"><i class="bi bi-building"></i>Company</h3>
            <div class="row g-3">
                <FormField class="col-md-6" label="Company" :error="errors.company">
                    <input v-model="form.company" type="text" placeholder="Acme Inc." class="form-control" :class="cls('company')" />
                </FormField>
                <FormField class="col-md-6" label="Job title" :error="errors.job_title">
                    <input v-model="form.job_title" type="text" placeholder="Head of Sales" class="form-control" :class="cls('job_title')" />
                </FormField>
            </div>
        </section>

        <section class="lead-form__section">
            <h3 class="lead-form__title"><i class="bi bi-bullseye"></i>Lead details</h3>
            <div class="row g-3">
                <FormField class="col-md-6" label="Source" :error="errors.source">
                    <select v-model="form.source" class="form-select" :class="cls('source')">
                        <option v-for="s in LEAD_SOURCES" :key="s.value" :value="s.value">{{ s.label }}</option>
                    </select>
                </FormField>
                <FormField class="col-md-6" label="Owner" :error="errors.assigned_to">
                    <select v-model="form.assigned_to" class="form-select" :class="cls('assigned_to')">
                        <option value="">Unassigned</option>
                        <option v-for="o in owners" :key="o.id" :value="o.id">{{ o.name }}</option>
                    </select>
                </FormField>
                <FormField class="col-12" label="Estimated value" :error="errors.estimated_value || errors.currency">
                    <div class="input-group">
                        <select v-model="form.currency" class="form-select lead-form__currency" aria-label="Currency" :class="cls('currency')">
                            <option v-for="c in currencyOptions" :key="c" :value="c">{{ c }}</option>
                        </select>
                        <input
                            v-model="form.estimated_value"
                            type="number"
                            min="0"
                            step="0.01"
                            placeholder="0.00"
                            class="form-control"
                            :class="cls('estimated_value')"
                        />
                    </div>
                </FormField>
                <FormField class="col-12" label="Score" :error="errors.score" hint="0 (cold) to 100 (hot)">
                    <div class="d-flex align-items-center gap-3">
                        <input v-model="form.score" type="range" min="0" max="100" step="5" class="form-range flex-grow-1" aria-label="Lead score" />
                        <span class="lead-form__score" :class="`is-${scoreLevel.key}`">{{ form.score }} · {{ scoreLevel.label }}</span>
                    </div>
                </FormField>
            </div>
        </section>

        <section class="lead-form__section">
            <h3 class="lead-form__title"><i class="bi bi-journal-text"></i>Notes</h3>
            <FormField label="Notes" :error="errors.notes">
                <textarea v-model="form.notes" rows="4" placeholder="Anything worth remembering about this lead…" class="form-control" :class="cls('notes')"></textarea>
            </FormField>
        </section>
        <div class="lead-form__actions">
            <button type="submit" class="btn btn-primary" :disabled="saving">
                <span v-if="saving" class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>
                {{ isEdit ? 'Save Changes' : 'Create Lead' }}
            </button>
            <button v-if="!isEdit" type="submit" data-action="another" class="btn btn-link text-decoration-none" :disabled="saving">
                Save &amp; add another
            </button>

        </div>
        <!-- =====================================================
     IMPORT FROM CSV
===================================================== -->

<div class="csv-card mt-4">

    <div class="csv-card-header">
        Import From CSV
    </div>

    <div class="csv-card-body">

        <!-- Error message -->

        <div
            v-if="csvError"
            class="csv-error"
        >
            {{ csvError }}
        </div>


        <!-- Demo CSV -->

        <button
            type="button"
            class="demo-csv-btn"
            @click="downloadDemoCsv"
        >
            <i class="bi bi-download me-2"></i>
            Demo CSV
        </button>


        <!-- File input -->

        <div class="csv-file-wrapper">

            <label
                for="lead-csv-file"
                class="csv-file-label"
            >
                <span class="csv-file-button">
                    Choose File
                </span>

                <span class="csv-file-name">
                    {{ csvFile?.name || 'No file chosen' }}
                </span>
            </label>

            <input
                id="lead-csv-file"
                ref="csvInput"
                type="file"
                accept=".csv,text/csv"
                class="csv-file-input"
                @change="handleCsvFile"
            />

        </div>


        <!-- Import button -->

        <button
            type="button"
            class="btn btn-primary w-100 csv-import-btn"
            :disabled="!csvFile || csvImporting"
            @click="importCsv"
        >
            {{
                csvImporting
                    ? 'Importing...'
                    : 'Import From CSV'
            }}
        </button>

    </div>

</div>
    </form>
</template>

<style scoped>
.lead-form {
    min-width: 0;
}

.lead-form__section + .lead-form__section {
    padding-top: 1.5rem;
    margin-top: 1.5rem;
    border-top: 1px solid var(--crm-border, #e4e7ec);
}

.lead-form__title {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin: 0 0 1rem;
    font-size: 0.8125rem;
    font-weight: 600;
    color: var(--crm-text-strong, #344054);
}

.lead-form__title i {
    font-size: 1rem;
    color: var(--crm-text-muted, #667085);
}

.lead-form__actions {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    margin-top: 1.75rem;
}

.lead-form__actions .btn-primary {
    min-height: 2.75rem;
}

/* Standard control size, matching the rest of the app */
.lead-form :deep(.form-control),
.lead-form :deep(.form-select) {
    min-height: var(--crm-control-h, 2.25rem);
    font-size: var(--crm-fs, 0.875rem);
}

.lead-form :deep(.form-control),
.lead-form :deep(.form-select) {
    background-color: var(--crm-bg, #f9fafb);
}

.lead-form :deep(.form-control:focus),
.lead-form :deep(.form-select:focus) {
    background-color: #fff;
}

.lead-form :deep(textarea.form-control) {
    min-height: 6rem;
    resize: vertical;
}

.lead-form :deep(.form-label) {
    margin-bottom: 0.25rem;
    font-size: 0.8125rem;
    font-weight: 500;
    color: var(--crm-text-strong, #344054);
}

.lead-form :deep(.form-text) {
    font-size: 0.75rem;
}

.lead-form .btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: var(--crm-control-h, 2.25rem);
    font-size: var(--crm-fs, 0.875rem);
    font-weight: 500;
}

.lead-form__currency {
    flex: 0 0 5.5rem;
}

.lead-form__score {
    flex: none;
    padding: 0.125rem 0.625rem;
    font-size: 0.75rem;
    font-weight: 600;
    font-variant-numeric: tabular-nums;
    border-radius: 999px;
}

.lead-form__score.is-cold {
    color: #1d4ed8;
    background: #e7f1ff;
}

.lead-form__score.is-warm {
    color: #b54708;
    background: #fef0c7;
}

.lead-form__score.is-hot {
    color: #b42318;
    background: #fee4e2;
}

.csv-card {
    border: 1px solid #e1e5ea;
    border-radius: 12px;
    overflow: hidden;
    background: #fff;
}

.csv-card-header {
    padding: 20px 24px;

    border-bottom: 1px solid #e1e5ea;

    font-size: 25px;
    font-weight: 700;

    color: #30343b;
}

.csv-card-body {
    padding: 28px 16px 16px;

    text-align: center;
}

.csv-error {
    margin-bottom: 16px;

    color: #ff2d2d;

    font-size: 18px;
}

.demo-csv-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;

    min-width: 224px;
    height: 54px;

    margin-bottom: 36px;

    border: 0;
    border-radius: 8px;

    background: #18b8ad;
    color: #fff;

    font-size: 19px;
    font-weight: 600;

    cursor: pointer;
}

.csv-file-wrapper {
    position: relative;

    width: 70%;
    min-width: 350px;

    height: 80px;

    margin: 0 auto 24px;

    border: 1px solid #6b7c9b;
    border-radius: 12px;

    overflow: hidden;
}

.csv-file-input {
    position: absolute;

    width: 1px;
    height: 1px;

    opacity: 0;
}

.csv-file-label {
    height: 100%;

    display: flex;
    align-items: center;

    padding: 0 36px;

    cursor: pointer;
}

.csv-file-button {
    padding: 14px 24px;

    border-radius: 28px;

    background: #eef5ff;

    color: #2563eb;

    font-size: 18px;
    font-weight: 500;

    white-space: nowrap;
}

.csv-file-name {
    margin-left: 24px;

    color: #202124;

    font-size: 18px;

    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.csv-import-btn {
    height: 56px;

    border-radius: 9px;

    font-size: 18px;
    font-weight: 600;
}

.csv-import-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}
</style>