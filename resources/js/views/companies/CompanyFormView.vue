<script setup>
import { computed, nextTick, onMounted, reactive, ref, watch } from 'vue';
import { companiesApi, companyLookupsApi } from '@/api/companies';
import { COMPANY_SIZES, CURRENCIES } from '@/constants';
import { useToastStore } from '@/stores/toast';
import { parseApiError } from '@/utils/errors';
import FormField from '@/components/FormField.vue';
import LoadingBlock from '@/components/LoadingBlock.vue';
import LookupSelect from '@/components/LookupSelect.vue';

const props = defineProps({
    /** null = create a new company, otherwise edit this company. */
    companyId: { type: [String, Number], default: null },
    owners: { type: Array, default: () => [] },
    industries: { type: Array, default: () => [] },
    types: { type: Array, default: () => [] },
});
const emit = defineEmits(['saved', 'cancel', 'lookup-created']);

const toast = useToastStore();

/* -------------------------------------------------------------------------- */
/* Field configuration (templates render from these, so a new field is one line) */
/* -------------------------------------------------------------------------- */

const STEPS = [
    { id: 1, label: 'Company Information' },
    { id: 2, label: 'Billing Information' },
];

const ADDRESS_FIELDS = [
    { part: 'street', label: 'Street', col: 'col-12', placeholder: '123 Market Street' },
    { part: 'city', label: 'City', col: 'col-md-6', placeholder: 'Dhaka' },
    { part: 'state', label: 'State / Province', col: 'col-md-6', placeholder: 'Dhaka Division' },
    { part: 'zip', label: 'Zip code', col: 'col-md-6', placeholder: '1212' },
    { part: 'country', label: 'Country', col: 'col-md-6', placeholder: 'Bangladesh' },
];
const ADDRESS_PARTS = ADDRESS_FIELDS.map((f) => f.part);
const ADDRESS_GROUPS = [
    { key: 'billing', title: 'Billing address', icon: 'bi-receipt' },
    { key: 'shipping', title: 'Shipping address', icon: 'bi-truck' },
];

const SOCIAL_FIELDS = [
    { key: 'linkedin', label: 'LinkedIn', icon: 'bi-linkedin', placeholder: 'linkedin.com/company/acme' },
    { key: 'twitter', label: 'Twitter / X', icon: 'bi-twitter-x', placeholder: 'x.com/acme' },
    { key: 'instagram', label: 'Instagram', icon: 'bi-instagram', placeholder: 'instagram.com/acme' },
    { key: 'facebook', label: 'Facebook', icon: 'bi-facebook', placeholder: 'facebook.com/acme' },
];

const addressKey = (group, part) => `${group}_${part}`;
const ADDRESS_KEYS = ADDRESS_GROUPS.flatMap((g) => ADDRESS_PARTS.map((part) => addressKey(g.key, part)));

const FIELDS = [
    'name', 'owner_id', 'parent_id', 'industry_id', 'company_type_id', 'size', 'annual_revenue', 'currency',
    'phone', 'email', 'website', ...SOCIAL_FIELDS.map((f) => f.key),
    ...ADDRESS_KEYS,
    'description',
];
const REQUIRED_FIELDS = ['name', 'currency'];
const DEFAULT_CURRENCY = 'USD';

const blank = () => Object.fromEntries(FIELDS.map((f) => [f, f === 'currency' ? DEFAULT_CURRENCY : '']));

const fromCompany = (company) =>
    Object.fromEntries(FIELDS.map((f) => [f, company[f] ?? (f === 'currency' ? DEFAULT_CURRENCY : '')]));

/** Empty inputs become null so optional fields can be cleared on update. */
function toPayload(values) {
    const body = { ...values };
    for (const key of FIELDS) if (!REQUIRED_FIELDS.includes(key) && body[key] === '') body[key] = null;
    return body;
}

/* -------------------------------------------------------------------------- */
/* State                                                                      */
/* -------------------------------------------------------------------------- */

const isEdit = computed(() => props.companyId !== null);
const form = reactive(blank());
const errors = ref({});
const loading = ref(isEdit.value);
const saving = ref(false);
const formEl = ref(null);

// Wizard
const step = ref(1);
const isLastStep = computed(() => step.value === STEPS.length);

/** Which step a field lives on (used to jump to the step holding a server error). */
const stepOfField = (field) => (ADDRESS_KEYS.includes(field) ? 2 : 1);

// Unsaved-changes tracking: the drawer asks before discarding a dirty form.
const serialize = () => JSON.stringify(form);
const snapshot = ref(serialize());
const isDirty = computed(() => serialize() !== snapshot.value);
defineExpose({ isDirty });

const cls = (field) => ({ 'is-invalid': Boolean(errors.value[field]) });

const currencyOptions = computed(() => (CURRENCIES.includes(form.currency) ? CURRENCIES : [form.currency, ...CURRENCIES]));

/* -------------------------------------------------------------------------- */
/* Step navigation                                                            */
/* -------------------------------------------------------------------------- */

const scrollToTop = () => formEl.value?.scrollIntoView({ block: 'start' });

async function goTo(target) {
    step.value = target;
    await nextTick();
    scrollToTop();
}

/** Only the current step's inputs are in the DOM, so this validates just that step. */
const currentStepValid = () => formEl.value?.reportValidity() ?? true;

function goNext() {
    if (currentStepValid()) goTo(Math.min(step.value + 1, STEPS.length));
}

const goBack = () => goTo(Math.max(step.value - 1, 1));

/** Stepper click: going back is free, going forward needs the current step to be valid. */
function onStepClick(target) {
    if (target === step.value) return;
    if (target < step.value) goTo(target);
    else if (currentStepValid()) goTo(target);
}

/* -------------------------------------------------------------------------- */
/* Parent company dropdown                                                    */
/* -------------------------------------------------------------------------- */

const parents = ref([]);
const currentParent = ref(null); // keeps the saved parent selectable even if it falls outside the options cap

const parentOptions = computed(() =>
    currentParent.value && !parents.value.some((p) => p.id === currentParent.value.id)
        ? [currentParent.value, ...parents.value]
        : parents.value,
);

async function loadParents() {
    try {
        parents.value = await companiesApi.options(isEdit.value ? { exclude: props.companyId } : {});
    } catch {
        /* the parent dropdown just stays empty */
    }
}

/* -------------------------------------------------------------------------- */
/* Copy billing information                                                   */
/* -------------------------------------------------------------------------- */

const sameAsBilling = ref(false);

function copyBilling() {
    for (const part of ADDRESS_PARTS) form[addressKey('shipping', part)] = form[addressKey('billing', part)];
}

// While the box is ticked, shipping mirrors billing as the user types.
watch(
    () => ADDRESS_PARTS.map((part) => form[addressKey('billing', part)]),
    () => sameAsBilling.value && copyBilling(),
);
watch(sameAsBilling, (on) => on && copyBilling());

/** On edit: tick the box again when the saved shipping address is identical to billing. */
function shippingMatchesBilling() {
    const hasBilling = ADDRESS_PARTS.some((part) => form[addressKey('billing', part)] !== '');
    return hasBilling && ADDRESS_PARTS.every((part) => form[addressKey('shipping', part)] === form[addressKey('billing', part)]);
}

const isMirrored = (group) => group === 'shipping' && sameAsBilling.value;

/* -------------------------------------------------------------------------- */
/* Lookups ("Industry +", "Company Type +")                                   */
/* -------------------------------------------------------------------------- */

const createIndustry = (name) => companyLookupsApi.create('industries', name);
const createType = (name) => companyLookupsApi.create('company-types', name);
const onLookupCreated = (kind, item) => emit('lookup-created', { kind, item });

/* -------------------------------------------------------------------------- */
/* Load (edit mode)                                                           */
/* -------------------------------------------------------------------------- */

onMounted(async () => {
    loadParents();

    if (!isEdit.value) return;

    try {
        const company = await companiesApi.get(props.companyId);
        Object.assign(form, fromCompany(company));
        currentParent.value = company.parent ?? null;
        sameAsBilling.value = shippingMatchesBilling();
        snapshot.value = serialize();
    } catch {
        toast.error('Could not load the company.');
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
        const saved = isEdit.value ? await companiesApi.update(props.companyId, body) : await companiesApi.create(body);
        toast.success(isEdit.value ? 'Company updated.' : 'Company created.');

        const keepOpen = addAnother && !isEdit.value;
        if (keepOpen) {
            // Keep owner and currency: they rarely change between consecutive entries.
            const { owner_id, currency } = form;
            Object.assign(form, blank(), { owner_id, currency });
            sameAsBilling.value = false;
            step.value = 1;
        }
        snapshot.value = serialize();

        emit('saved', saved, { another: keepOpen });
        if (keepOpen) await nextTick(focusFirstField);
    } catch (e) {
        const parsed = parseApiError(e);
        errors.value = parsed.fields;
        toast.error(parsed.message);

        // Jump back to the earliest step that has an error so it is visible.
        const failedSteps = Object.keys(parsed.fields ?? {}).map(stepOfField);
        if (failedSteps.length) step.value = Math.min(...failedSteps);

        await nextTick(focusFirstInvalid);
    } finally {
        saving.value = false;
    }
}

function onSubmit(event) {
    const action = event.submitter?.dataset.action;

    // Enter key / "Next" on a non-final step just advances the wizard.
    // (Edit mode also offers "Save changes" on every step.)
    if (!isLastStep.value && action !== 'save') return goNext();

    return submit(action === 'another');
}
</script>

<template>
    <LoadingBlock v-if="loading" />

    <form v-else ref="formEl" class="company-form" @submit.prevent="onSubmit">
        <!-- Stepper -->
        <ol class="company-form__steps" aria-label="Progress">
            <template v-for="(s, i) in STEPS" :key="s.id">
                <li v-if="i > 0" class="company-form__connector" aria-hidden="true"></li>
                <li>
                    <button
                        type="button"
                        class="company-form__step"
                        :class="{ 'is-active': step === s.id, 'is-done': step > s.id }"
                        :aria-current="step === s.id ? 'step' : undefined"
                        @click="onStepClick(s.id)"
                    >
                        <span class="company-form__step-dot">
                            <i v-if="step > s.id" class="bi bi-check-lg"></i>
                            <template v-else>{{ s.id }}</template>
                        </span>
                        <span class="company-form__step-label">{{ s.label }}</span>
                    </button>
                </li>
            </template>
        </ol>

        <!-- ============================ STEP 1 ============================ -->
        <template v-if="step === 1">
            <!-- Company -->
            <section class="company-form__section">
                <h3 class="company-form__title"><i class="bi bi-building"></i>Company information</h3>
                <div class="row g-3">
                    <FormField class="col-12" label="Company name" required :error="errors.name">
                        <input v-model="form.name" type="text" required maxlength="255" placeholder="Acme Inc." class="form-control" :class="cls('name')" />
                    </FormField>

                    <FormField class="col-md-6" label="Company owner" :error="errors.owner_id">
                        <select v-model="form.owner_id" class="form-select" :class="cls('owner_id')">
                            <option value="">Unassigned</option>
                            <option v-for="o in owners" :key="o.id" :value="o.id">{{ o.name }}</option>
                        </select>
                    </FormField>

                    <FormField class="col-md-6" label="Parent company" :error="errors.parent_id">
                        <select v-model="form.parent_id" class="form-select" :class="cls('parent_id')">
                            <option value="">None</option>
                            <option v-for="p in parentOptions" :key="p.id" :value="p.id">{{ p.name }}</option>
                        </select>
                    </FormField>

                    <FormField class="col-md-6" label="Industry" :error="errors.industry_id">
                        <LookupSelect
                            v-model="form.industry_id"
                            :options="industries"
                            :create="createIndustry"
                            :invalid="Boolean(errors.industry_id)"
                            placeholder="Select industry"
                            add-label="Add industry"
                            add-placeholder="New industry"
                            @created="onLookupCreated('industries', $event)"
                        />
                    </FormField>

                    <FormField class="col-md-6" label="Company type" :error="errors.company_type_id">
                        <LookupSelect
                            v-model="form.company_type_id"
                            :options="types"
                            :create="createType"
                            :invalid="Boolean(errors.company_type_id)"
                            placeholder="Select type"
                            add-label="Add company type"
                            add-placeholder="New company type"
                            @created="onLookupCreated('company-types', $event)"
                        />
                    </FormField>

                    <FormField class="col-md-6" label="Company size" :error="errors.size">
                        <select v-model="form.size" class="form-select" :class="cls('size')">
                            <option value="">Select size</option>
                            <option v-for="s in COMPANY_SIZES" :key="s.value" :value="s.value">{{ s.label }}</option>
                        </select>
                    </FormField>

                    <FormField class="col-md-6" label="Annual revenue" :error="errors.annual_revenue || errors.currency">
                        <div class="input-group">
                            <select v-model="form.currency" class="form-select company-form__currency" aria-label="Currency" :class="cls('currency')">
                                <option v-for="c in currencyOptions" :key="c" :value="c">{{ c }}</option>
                            </select>
                            <input
                                v-model="form.annual_revenue"
                                type="number"
                                min="0"
                                step="0.01"
                                placeholder="0.00"
                                class="form-control"
                                :class="cls('annual_revenue')"
                            />
                        </div>
                    </FormField>
                </div>
            </section>

            <!-- Contact -->
            <section class="company-form__section">
                <h3 class="company-form__title"><i class="bi bi-telephone"></i>Contact</h3>
                <div class="row g-3">
                    <FormField class="col-md-6" label="Phone" :error="errors.phone">
                        <input v-model="form.phone" type="tel" placeholder="+880 1700 000000" class="form-control" :class="cls('phone')" />
                    </FormField>
                    <FormField class="col-md-6" label="Email" :error="errors.email">
                        <input v-model="form.email" type="email" placeholder="info@acme.com" class="form-control" :class="cls('email')" />
                    </FormField>
                    <FormField class="col-12" label="Website" :error="errors.website">
                        <input v-model="form.website" type="text" inputmode="url" placeholder="acme.com" class="form-control" :class="cls('website')" />
                    </FormField>
                </div>
            </section>

            <!-- Social -->
            <section class="company-form__section">
                <h3 class="company-form__title"><i class="bi bi-share"></i>Social profiles</h3>
                <div class="row g-3">
                    <FormField v-for="s in SOCIAL_FIELDS" :key="s.key" class="col-md-6" :label="s.label" :error="errors[s.key]">
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi" :class="s.icon"></i></span>
                            <input v-model="form[s.key]" type="text" inputmode="url" :placeholder="s.placeholder" class="form-control" :class="cls(s.key)" />
                        </div>
                    </FormField>
                </div>
            </section>

            <!-- Description -->
            <section class="company-form__section">
                <h3 class="company-form__title"><i class="bi bi-journal-text"></i>Description</h3>
                <FormField label="Description" :error="errors.description">
                    <textarea
                        v-model="form.description"
                        rows="4"
                        maxlength="5000"
                        placeholder="Anything worth remembering about this company…"
                        class="form-control"
                        :class="cls('description')"
                    ></textarea>
                </FormField>
            </section>
        </template>

        <!-- ============================ STEP 2 ============================ -->
        <template v-else>
            <section v-for="g in ADDRESS_GROUPS" :key="g.key" class="company-form__section">
                <div class="company-form__title-row">
                    <h3 class="company-form__title mb-0"><i class="bi" :class="g.icon"></i>{{ g.title }}</h3>

                    <div v-if="g.key === 'shipping'" class="form-check mb-0">
                        <input id="company-copy-billing" v-model="sameAsBilling" type="checkbox" class="form-check-input" />
                        <label for="company-copy-billing" class="form-check-label">Copy billing information</label>
                    </div>
                </div>

                <div class="row g-3">
                    <FormField
                        v-for="f in ADDRESS_FIELDS"
                        :key="f.part"
                        :class="f.col"
                        :label="f.label"
                        :error="errors[addressKey(g.key, f.part)]"
                    >
                        <input
                            v-model="form[addressKey(g.key, f.part)]"
                            type="text"
                            :disabled="isMirrored(g.key)"
                            :placeholder="f.placeholder"
                            class="form-control"
                            :class="cls(addressKey(g.key, f.part))"
                        />
                    </FormField>
                </div>
            </section>
        </template>

        <!-- Actions -->
        <div class="company-form__actions">
            <!-- Step 1 -->
            <template v-if="!isLastStep">
                <button type="submit" data-action="next" class="btn btn-primary">Next</button>
                <button v-if="isEdit" type="submit" data-action="save" class="btn btn-link text-decoration-none" :disabled="saving">
                    <span v-if="saving" class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>
                    Save changes
                </button>
            </template>

            <!-- Final step -->
            <template v-else>
                <button type="submit" data-action="save" class="btn btn-primary" :disabled="saving">
                    <span v-if="saving" class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>
                    {{ isEdit ? 'Save Changes' : 'Create Company' }}
                </button>
                <button v-if="!isEdit" type="submit" data-action="another" class="btn btn-link text-decoration-none" :disabled="saving">
                    Save &amp; add another
                </button>
                <button type="button" class="btn btn-link text-decoration-none" :disabled="saving" @click="goBack">
                    Back
                </button>
            </template>
        </div>
    </form>
</template>

<style scoped>
.company-form {
    min-width: 0;
}

/* ------------------------------ Stepper ------------------------------ */
.company-form__steps {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.75rem;
    margin: 0 0 1.75rem;
    padding: 0;
    list-style: none;
}

.company-form__connector {
    flex: 0 1 3rem;
    height: 1px;
    background: var(--crm-border, #e4e7ec);
}

.company-form__step {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.25rem;
    border: 0;
    background: none;
    font-size: var(--crm-fs, 0.875rem);
    color: var(--crm-text-muted, #667085);
    cursor: pointer;
}

.company-form__step:focus-visible {
    outline: 2px solid var(--bs-primary, #2f6fed);
    outline-offset: 2px;
    border-radius: 0.5rem;
}

.company-form__step-dot {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex: 0 0 auto;
    width: 1.75rem;
    height: 1.75rem;
    border-radius: 50%;
    font-size: var(--crm-fs-sm, 0.8125rem);
    font-weight: 500;
    color: var(--crm-text-muted, #667085);
    background: var(--crm-hover, #f2f4f7);
}

.company-form__step.is-active {
    color: var(--crm-text-strong, #344054);
    font-weight: 500;
}

.company-form__step.is-active .company-form__step-dot,
.company-form__step.is-done .company-form__step-dot {
    color: #fff;
    background: var(--bs-primary, #2f6fed);
}

.company-form__step.is-done {
    color: var(--crm-text-strong, #344054);
}

/* Narrow drawers: show only the active step's label */
@media (max-width: 575.98px) {
    .company-form__step:not(.is-active) .company-form__step-label {
        display: none;
    }
    .company-form__connector {
        flex-basis: 1.5rem;
    }
}

/* ------------------------------ Sections ----------------------------- */
.company-form__section + .company-form__section {
    padding-top: 1.5rem;
    margin-top: 1.5rem;
    border-top: 1px solid var(--crm-border, #e4e7ec);
}

.company-form__title {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin: 0 0 1rem;
    font-size: var(--crm-fs-sm, 0.8125rem);
    font-weight: 600;
    color: var(--crm-text-strong, #344054);
}

.company-form__title i {
    font-size: 1rem;
    color: var(--crm-text-muted, #667085);
}

.company-form__title-row {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem 1rem;
    margin-bottom: 1rem;
}

.company-form__title-row .company-form__title {
    margin-bottom: 0;
}

.company-form__title-row .form-check-label {
    font-size: var(--crm-fs-sm, 0.8125rem);
    font-weight: 500;
    color: var(--crm-text-strong, #344054);
}

.company-form__actions {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    margin-top: 1.75rem;
}

.company-form__actions .btn-primary {
    min-height: 2.75rem;
}

/* Standard control size, matching the rest of the app */
.company-form :deep(.form-control),
.company-form :deep(.form-select) {
    min-height: var(--crm-control-h, 2.25rem);
    font-size: var(--crm-fs, 0.875rem);
    background-color: var(--crm-bg, #f9fafb);
}

.company-form :deep(.form-control:focus),
.company-form :deep(.form-select:focus) {
    background-color: #fff;
}

.company-form :deep(.form-control:disabled) {
    color: var(--crm-text-muted, #667085);
    background-color: var(--crm-hover, #f2f4f7);
}

.company-form :deep(textarea.form-control) {
    min-height: 6rem;
    resize: vertical;
}

.company-form :deep(.form-label) {
    margin-bottom: 0.25rem;
    font-size: var(--crm-fs-sm, 0.8125rem);
    font-weight: 500;
    color: var(--crm-text-strong, #344054);
}

.company-form :deep(.form-text) {
    font-size: var(--crm-fs-xs, 0.75rem);
}

.company-form :deep(.input-group-text) {
    color: var(--crm-text-muted, #667085);
    background-color: var(--crm-hover, #f2f4f7);
}

.company-form :deep(.btn) {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: var(--crm-control-h, 2.25rem);
    font-size: var(--crm-fs, 0.875rem);
    font-weight: 500;
}

.company-form__currency {
    flex: 0 0 5.5rem;
}
</style>