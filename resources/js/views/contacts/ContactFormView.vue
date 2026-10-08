<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import { companiesApi, companyLookupsApi } from '@/api/companies';
import { contactLookupsApi, contactsApi } from '@/api/contacts';
import { useToastStore } from '@/stores/toast';
import { parseApiError } from '@/utils/errors';
import FormField from '@/components/FormField.vue';
import LoadingBlock from '@/components/LoadingBlock.vue';
import LookupSelect from '@/components/LookupSelect.vue';

const props = defineProps({
    /** null = create a new contact, otherwise edit this contact. */
    contactId: { type: [String, Number], default: null },
    owners: { type: Array, default: () => [] },
    industries: { type: Array, default: () => [] },
    sources: { type: Array, default: () => [] },
    stages: { type: Array, default: () => [] },
});
const emit = defineEmits(['saved', 'cancel', 'lookup-created']);

const toast = useToastStore();
const router = useRouter();

/* -------------------------------------------------------------------------- */
/* Field configuration (templates render from these, so a new field is one line) */
/* -------------------------------------------------------------------------- */

const STEPS = [
    { key: 'basic', title: 'Basic information' },
    { key: 'details', title: 'Address & social' },
];

const ADDRESS_FIELDS = [
    { part: 'address', label: 'Address', col: 'col-12', placeholder: 'House 12, Road 5, Banani' },
    { part: 'city', label: 'City', col: 'col-md-6', placeholder: 'Dhaka' },
    { part: 'state', label: 'State / Province', col: 'col-md-6', placeholder: 'Dhaka Division' },
    { part: 'zip', label: 'Zip code', col: 'col-md-6', placeholder: '1212' },
    { part: 'country', label: 'Country', col: 'col-md-6', placeholder: 'Bangladesh' },
];
const ADDRESS_PARTS = ADDRESS_FIELDS.map((f) => f.part);
const ADDRESS_GROUPS = [
    { key: 'present', title: 'Present address', icon: 'bi-house' },
    { key: 'permanent', title: 'Permanent address', icon: 'bi-geo-alt' },
];

const SOCIAL_FIELDS = [
    { key: 'linkedin', label: 'LinkedIn', icon: 'bi-linkedin', placeholder: 'linkedin.com/in/jane-doe' },
    { key: 'twitter', label: 'Twitter / X', icon: 'bi-twitter-x', placeholder: 'x.com/janedoe' },
];

const addressKey = (group, part) => `${group}_${part}`;

// Which fields live on which step (used for validation and to jump to the step with a server error).
const STEP_FIELDS = [
    [
        'first_name', 'last_name', 'owner_id', 'email', 'birthday', 'company_id', 'job_title', 'phone',
        'department', 'industry_id', 'contact_source_id', 'contact_stage_id',
    ],
    [
        ...ADDRESS_GROUPS.flatMap((g) => ADDRESS_PARTS.map((part) => addressKey(g.key, part))),
        ...SOCIAL_FIELDS.map((f) => f.key),
        'description',
    ],
];
const FIELDS = STEP_FIELDS.flat();
const REQUIRED_FIELDS = ['first_name', 'last_name'];
const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

const blank = () => Object.fromEntries(FIELDS.map((f) => [f, '']));
const fromContact = (contact) => Object.fromEntries(FIELDS.map((f) => [f, contact[f] ?? '']));

/** Empty inputs become null so optional fields can be cleared on update. */
function toPayload(values) {
    const body = { ...values };
    for (const key of FIELDS) if (!REQUIRED_FIELDS.includes(key) && body[key] === '') body[key] = null;
    return body;
}

const stepOf = (field) => STEP_FIELDS.findIndex((fields) => fields.includes(field));

/* -------------------------------------------------------------------------- */
/* State                                                                      */
/* -------------------------------------------------------------------------- */

const isEdit = computed(() => props.contactId !== null);
const form = reactive(blank());
const errors = ref({});
const loading = ref(isEdit.value);
const saving = ref(false);
const formEl = ref(null);
const step = ref(0);
const isLastStep = computed(() => step.value === STEPS.length - 1);
const today = new Date().toISOString().slice(0, 10); // upper bound for the birthday picker

// Unsaved-changes tracking: the drawer asks before discarding a dirty form.
const serialize = () => JSON.stringify(form);
const snapshot = ref(serialize());
const isDirty = computed(() => serialize() !== snapshot.value);
defineExpose({ isDirty });

const cls = (field) => ({ 'is-invalid': Boolean(errors.value[field]) });

const focusFirstField = () => formEl.value?.querySelector('input:not([type="hidden"])')?.focus();
const focusFirstInvalid = () => formEl.value?.querySelector('.is-invalid')?.focus();

/* -------------------------------------------------------------------------- */
/* Steps                                                                      */
/* -------------------------------------------------------------------------- */

/** Client-side check of step 1 so the user finds mistakes before moving on. The server stays the authority. */
function validateBasics() {
    const found = {};
    if (!form.first_name.trim()) found.first_name = 'First name is required.';
    if (!form.last_name.trim()) found.last_name = 'Last name is required.';
    if (form.email.trim() && !EMAIL_PATTERN.test(form.email.trim())) found.email = 'Enter a valid email address.';

    errors.value = found;
    return Object.keys(found).length === 0;
}

async function goNext() {
    if (!validateBasics()) {
        await nextTick(focusFirstInvalid);
        return;
    }
    step.value += 1;
    await nextTick(focusFirstField);
}

async function goBack() {
    step.value = Math.max(0, step.value - 1);
    await nextTick(focusFirstField);
}

/** The step header buttons: going back is always allowed, going forward needs a valid step 1. */
async function goTo(index) {
    if (index === step.value) return;
    if (index < step.value) return goBack();
    return goNext();
}

/* -------------------------------------------------------------------------- */
/* Company dropdown (+ "add company" opens the company create page)           */
/* -------------------------------------------------------------------------- */

const companies = ref([]);
const currentCompany = ref(null); // keeps the saved company selectable even if it falls outside the options cap

const companyOptions = computed(() =>
    currentCompany.value && !companies.value.some((c) => c.id === currentCompany.value.id)
        ? [currentCompany.value, ...companies.value]
        : companies.value,
);

async function loadCompanies() {
    try {
        companies.value = await companiesApi.options();
    } catch {
        /* the company dropdown just stays empty */
    }
}

// Opens in a new tab so this form (and anything typed in it) is never lost; the list refreshes on return.
const createCompanyHref = computed(() => router.resolve({ name: 'companies.create' }).href);

/* -------------------------------------------------------------------------- */
/* Copy present address                                                       */
/* -------------------------------------------------------------------------- */

const sameAsPresent = ref(false);

function copyPresent() {
    for (const part of ADDRESS_PARTS) form[addressKey('permanent', part)] = form[addressKey('present', part)];
}

// While the box is ticked, permanent mirrors present as the user types.
watch(
    () => ADDRESS_PARTS.map((part) => form[addressKey('present', part)]),
    () => sameAsPresent.value && copyPresent(),
);
watch(sameAsPresent, (on) => on && copyPresent());

/** On edit: tick the box again when the saved permanent address is identical to present. */
function permanentMatchesPresent() {
    const hasPresent = ADDRESS_PARTS.some((part) => form[addressKey('present', part)] !== '');
    return hasPresent && ADDRESS_PARTS.every((part) => form[addressKey('permanent', part)] === form[addressKey('present', part)]);
}

const isMirrored = (group) => group === 'permanent' && sameAsPresent.value;

/* -------------------------------------------------------------------------- */
/* Lookups ("Industry +", "Contact Source +", "Contact Stage +")              */
/* -------------------------------------------------------------------------- */

// Industries are shared with companies, so creating one here makes it available there too.
const createIndustry = (name) => companyLookupsApi.create('industries', name);
const createSource = (name) => contactLookupsApi.create('contact-sources', name);
const createStage = (name) => contactLookupsApi.create('contact-stages', name);
const onLookupCreated = (kind, item) => emit('lookup-created', { kind, item });

/* -------------------------------------------------------------------------- */
/* Load (edit mode)                                                           */
/* -------------------------------------------------------------------------- */

onMounted(async () => {
    window.addEventListener('focus', loadCompanies); // pick up a company created in the other tab
    loadCompanies();

    if (!isEdit.value) return;

    try {
        const contact = await contactsApi.get(props.contactId);
        Object.assign(form, fromContact(contact));
        currentCompany.value = contact.company ?? null;
        sameAsPresent.value = permanentMatchesPresent();
        snapshot.value = serialize();
    } catch {
        toast.error('Could not load the contact.');
        emit('cancel');
    } finally {
        loading.value = false;
    }
});

onBeforeUnmount(() => window.removeEventListener('focus', loadCompanies));

/* -------------------------------------------------------------------------- */
/* Submit                                                                     */
/* -------------------------------------------------------------------------- */

async function submit(addAnother) {
    if (!validateBasics()) {
        step.value = 0;
        await nextTick(focusFirstInvalid);
        return;
    }

    saving.value = true;
    errors.value = {};

    try {
        const body = toPayload(form);
        const saved = isEdit.value ? await contactsApi.update(props.contactId, body) : await contactsApi.create(body);
        toast.success(isEdit.value ? 'Contact updated.' : 'Contact created.');

        const keepOpen = addAnother && !isEdit.value;
        if (keepOpen) {
            // Keep owner and source: they rarely change between consecutive entries.
            const { owner_id, contact_source_id } = form;
            Object.assign(form, blank(), { owner_id, contact_source_id });
            sameAsPresent.value = false;
            step.value = 0;
        }
        snapshot.value = serialize();

        emit('saved', saved, { another: keepOpen });
        if (keepOpen) await nextTick(focusFirstField);
    } catch (e) {
        const parsed = parseApiError(e);
        errors.value = parsed.fields;
        toast.error(parsed.message);

        // Jump to the earliest step that holds an invalid field.
        const failing = Object.keys(parsed.fields).map(stepOf).filter((i) => i >= 0);
        if (failing.length) step.value = Math.min(...failing);
        await nextTick(focusFirstInvalid);
    } finally {
        saving.value = false;
    }
}

// Enter on step 1 moves forward; only the last step actually saves.
const onSubmit = (event) => (isLastStep.value ? submit(event.submitter?.dataset.action === 'another') : goNext());
</script>

<template>
    <LoadingBlock v-if="loading" />

    <form v-else ref="formEl" class="contact-form" novalidate @submit.prevent="onSubmit">
        <!-- Step indicator -->
        <ol class="contact-form__steps" aria-label="Form steps">
            <li v-for="(s, index) in STEPS" :key="s.key" class="contact-form__step-item">
                <button
                    type="button"
                    class="contact-form__step"
                    :class="{ 'is-active': index === step, 'is-done': index < step }"
                    :aria-current="index === step ? 'step' : null"
                    @click="goTo(index)"
                >
                    <span class="contact-form__step-index">
                        <i v-if="index < step" class="bi bi-check-lg" aria-hidden="true"></i>
                        <template v-else>{{ index + 1 }}</template>
                    </span>
                    {{ s.title }}
                </button>
            </li>
        </ol>

        <!-- Step 1: basic information -->
        <div v-show="step === 0">
            <section class="contact-form__section">
                <h3 class="contact-form__title"><i class="bi bi-person"></i>Contact information</h3>
                <div class="row g-3">
                    <FormField class="col-md-6" label="First name" required :error="errors.first_name">
                        <input v-model="form.first_name" type="text" maxlength="100" placeholder="Jane" class="form-control" :class="cls('first_name')" />
                    </FormField>

                    <FormField class="col-md-6" label="Last name" required :error="errors.last_name">
                        <input v-model="form.last_name" type="text" maxlength="100" placeholder="Doe" class="form-control" :class="cls('last_name')" />
                    </FormField>

                    <FormField class="col-md-6" label="Contact owner" :error="errors.owner_id">
                        <select v-model="form.owner_id" class="form-select" :class="cls('owner_id')">
                            <option value="">Unassigned</option>
                            <option v-for="o in owners" :key="o.id" :value="o.id">{{ o.name }}</option>
                        </select>
                    </FormField>

                    <FormField class="col-md-6" label="Email" :error="errors.email">
                        <input v-model="form.email" type="email" placeholder="jane@acme.com" class="form-control" :class="cls('email')" />
                    </FormField>

                    <FormField class="col-md-6" label="Birthday" :error="errors.birthday">
                        <input v-model="form.birthday" type="date" min="1900-01-02" :max="today" class="form-control" :class="cls('birthday')" />
                    </FormField>

                    <FormField class="col-md-6" label="Phone number" :error="errors.phone">
                        <input v-model="form.phone" type="tel" placeholder="+880 1700 000000" class="form-control" :class="cls('phone')" />
                    </FormField>
                </div>
            </section>

            <section class="contact-form__section">
                <h3 class="contact-form__title"><i class="bi bi-building"></i>Work</h3>
                <div class="row g-3">
                    <FormField class="col-md-6" label="Company" :error="errors.company_id">
                        <div class="input-group">
                            <select v-model="form.company_id" class="form-select" :class="cls('company_id')">
                                <option value="">No company</option>
                                <option v-for="c in companyOptions" :key="c.id" :value="c.id">{{ c.name }}</option>
                            </select>
                            <a
                                :href="createCompanyHref"
                                target="_blank"
                                rel="noopener"
                                class="btn btn-outline-secondary"
                                title="Add company"
                                aria-label="Add company (opens in a new tab)"
                            >
                                <i class="bi bi-plus-lg"></i>
                            </a>
                        </div>
                        <div class="form-text">Opens in a new tab; the list refreshes when you come back.</div>
                    </FormField>

                    <FormField class="col-md-6" label="Job title" :error="errors.job_title">
                        <input v-model="form.job_title" type="text" maxlength="150" placeholder="Head of Sales" class="form-control" :class="cls('job_title')" />
                    </FormField>

                    <FormField class="col-md-6" label="Department" :error="errors.department">
                        <input v-model="form.department" type="text" maxlength="100" placeholder="Sales" class="form-control" :class="cls('department')" />
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

                    <FormField class="col-md-6" label="Contact source" :error="errors.contact_source_id">
                        <LookupSelect
                            v-model="form.contact_source_id"
                            :options="sources"
                            :create="createSource"
                            :invalid="Boolean(errors.contact_source_id)"
                            placeholder="Select source"
                            add-label="Add contact source"
                            add-placeholder="New contact source"
                            @created="onLookupCreated('contact-sources', $event)"
                        />
                    </FormField>

                    <FormField class="col-md-6" label="Contact stage" :error="errors.contact_stage_id">
                        <LookupSelect
                            v-model="form.contact_stage_id"
                            :options="stages"
                            :create="createStage"
                            :invalid="Boolean(errors.contact_stage_id)"
                            placeholder="Select stage"
                            add-label="Add contact stage"
                            add-placeholder="New contact stage"
                            @created="onLookupCreated('contact-stages', $event)"
                        />
                    </FormField>
                </div>
            </section>
        </div>

        <!-- Step 2: address, social, notes -->
        <div v-show="step === 1">
            <section v-for="g in ADDRESS_GROUPS" :key="g.key" class="contact-form__section">
                <div class="contact-form__title-row">
                    <h3 class="contact-form__title mb-0"><i class="bi" :class="g.icon"></i>{{ g.title }}</h3>

                    <div v-if="g.key === 'permanent'" class="form-check mb-0">
                        <input id="contact-copy-present" v-model="sameAsPresent" type="checkbox" class="form-check-input" />
                        <label for="contact-copy-present" class="form-check-label">Same as present address</label>
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

            <section class="contact-form__section">
                <h3 class="contact-form__title"><i class="bi bi-share"></i>Social profiles</h3>
                <div class="row g-3">
                    <FormField v-for="s in SOCIAL_FIELDS" :key="s.key" class="col-md-6" :label="s.label" :error="errors[s.key]">
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi" :class="s.icon"></i></span>
                            <input v-model="form[s.key]" type="text" inputmode="url" :placeholder="s.placeholder" class="form-control" :class="cls(s.key)" />
                        </div>
                    </FormField>
                </div>
            </section>

            <section class="contact-form__section">
                <h3 class="contact-form__title"><i class="bi bi-journal-text"></i>Description</h3>
                <FormField label="Description" :error="errors.description">
                    <textarea
                        v-model="form.description"
                        rows="4"
                        maxlength="5000"
                        placeholder="Anything worth remembering about this contact…"
                        class="form-control"
                        :class="cls('description')"
                    ></textarea>
                </FormField>
            </section>
        </div>

        <!-- Actions -->
        <div class="contact-form__actions">
            <template v-if="!isLastStep">
                <button type="submit" class="btn btn-primary">Next<i class="bi bi-arrow-right ms-2"></i></button>
            </template>

            <template v-else>
                <div class="contact-form__nav">
                    <button type="button" class="btn btn-outline-secondary" :disabled="saving" @click="goBack">
                        <i class="bi bi-arrow-left me-2"></i>Back
                    </button>
                    <button type="submit" class="btn btn-primary flex-grow-1" :disabled="saving">
                        <span v-if="saving" class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>
                        {{ isEdit ? 'Save Changes' : 'Create Contact' }}
                    </button>
                </div>
                <button v-if="!isEdit" type="submit" data-action="another" class="btn btn-link text-decoration-none" :disabled="saving">
                    Save &amp; add another
                </button>
            </template>
        </div>
    </form>
</template>

<style scoped>
.contact-form {
    min-width: 0;
}

.contact-form__section + .contact-form__section {
    padding-top: 1.5rem;
    margin-top: 1.5rem;
    border-top: 1px solid var(--crm-border, #e4e7ec);
}

.contact-form__title {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin: 0 0 1rem;
    font-size: var(--crm-fs-sm, 0.8125rem);
    font-weight: 600;
    color: var(--crm-text-strong, #344054);
}

.contact-form__title i {
    font-size: 1rem;
    color: var(--crm-text-muted, #667085);
}

.contact-form__title-row {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 0.5rem 1rem;
    margin-bottom: 1rem;
}

.contact-form__title-row .contact-form__title {
    margin-bottom: 0;
}

.contact-form__title-row .form-check-label {
    font-size: var(--crm-fs-sm, 0.8125rem);
    font-weight: 500;
    color: var(--crm-text-strong, #344054);
}

.contact-form__actions {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    margin-top: 1.75rem;
}

.contact-form__actions .btn-primary {
    min-height: 2.75rem;
}

/* Standard control size, matching the rest of the app */
.contact-form :deep(.form-control),
.contact-form :deep(.form-select) {
    min-height: var(--crm-control-h, 2.25rem);
    font-size: var(--crm-fs, 0.875rem);
    background-color: var(--crm-bg, #f9fafb);
}

.contact-form :deep(.form-control:focus),
.contact-form :deep(.form-select:focus) {
    background-color: #fff;
}

.contact-form :deep(.form-control:disabled) {
    color: var(--crm-text-muted, #667085);
    background-color: var(--crm-hover, #f2f4f7);
}

.contact-form :deep(textarea.form-control) {
    min-height: 6rem;
    resize: vertical;
}

.contact-form :deep(.form-label) {
    margin-bottom: 0.25rem;
    font-size: var(--crm-fs-sm, 0.8125rem);
    font-weight: 500;
    color: var(--crm-text-strong, #344054);
}

.contact-form :deep(.form-text) {
    font-size: var(--crm-fs-xs, 0.75rem);
}

.contact-form :deep(.input-group-text) {
    color: var(--crm-text-muted, #667085);
    background-color: var(--crm-hover, #f2f4f7);
}

.contact-form :deep(.btn) {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: var(--crm-control-h, 2.25rem);
    font-size: var(--crm-fs, 0.875rem);
    font-weight: 500;
}

.contact-form__currency {
    flex: 0 0 5.5rem;
}

/* Step indicator */
.contact-form__steps {
    display: flex;
    gap: 0.75rem;
    padding: 0;
    margin: 0 0 1.5rem;
    list-style: none;
}

.contact-form__step-item {
    flex: 1 1 0;
    min-width: 0;
}

.contact-form__step {
    display: flex;
    align-items: center;
    gap: 0.625rem;
    width: 100%;
    padding: 0.625rem 0.75rem;
    font-size: var(--crm-fs-sm, 0.8125rem);
    font-weight: 500;
    color: var(--crm-text-muted, #667085);
    text-align: left;
    cursor: pointer;
    background: transparent;
    border: 1px solid var(--crm-border, #e4e7ec);
    border-radius: var(--crm-radius, 0.5rem);
    transition: background-color 0.15s ease, border-color 0.15s ease, color 0.15s ease;
}

.contact-form__step.is-active {
    color: var(--crm-primary);
    background: var(--crm-primary-soft);
    border-color: var(--crm-primary);
}

.contact-form__step.is-done {
    color: var(--crm-text-strong, #344054);
}

.contact-form__step-index {
    display: grid;
    flex: none;
    place-items: center;
    width: 1.5rem;
    height: 1.5rem;
    font-size: var(--crm-fs-xs, 0.75rem);
    font-weight: 600;
    background: var(--crm-hover, #f2f4f7);
    border-radius: 50%;
}

.contact-form__step.is-active .contact-form__step-index {
    color: #fff;
    background: var(--crm-primary);
}

.contact-form__nav {
    display: flex;
    gap: 0.5rem;
}
</style>
