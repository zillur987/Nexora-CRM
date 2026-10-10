<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import { companiesApi } from '@/api/companies';
import { contactLookupsApi, contactsApi } from '@/api/contacts';
import { dealLookupsApi, dealsApi } from '@/api/deals';
import { useToastStore } from '@/stores/toast';
import { CURRENCIES, DEFAULT_CURRENCY, PRIORITIES, stageProbability } from '@/utils/dealOptions';
import { parseApiError } from '@/utils/errors';
import { formatMoney } from '@/utils/money';
import FormField from '@/components/FormField.vue';
import LoadingBlock from '@/components/LoadingBlock.vue';
import LookupSelect from '@/components/LookupSelect.vue';

const props = defineProps({
    /** null = create a new deal, otherwise edit this deal. */
    dealId: { type: [String, Number], default: null },
    owners: { type: Array, default: () => [] },
    stages: { type: Array, default: () => [] },
    types: { type: Array, default: () => [] },
    sources: { type: Array, default: () => [] },
});
const emit = defineEmits(['saved', 'cancel', 'lookup-created']);

const toast = useToastStore();
const router = useRouter();

/* -------------------------------------------------------------------------- */
/* Field configuration (templates render from these, so a new field is one line) */
/* -------------------------------------------------------------------------- */

const STEPS = [
    { key: 'deal', title: 'Deal information' },
    { key: 'details', title: 'Relationships & details' },
];

// Which fields live on which step (used for validation and to jump to the step with a server error).
const STEP_FIELDS = [
    ['name', 'owner_id', 'deal_stage_id', 'amount', 'currency', 'probability', 'expected_close_date', 'actual_close_date', 'lost_reason'],
    ['company_id', 'contact_id', 'deal_type_id', 'lead_source_id', 'priority', 'next_step', 'description'],
];
const FIELDS = STEP_FIELDS.flat();
const REQUIRED_FIELDS = ['name', 'deal_stage_id', 'currency'];
const NUMBER_FIELDS = ['amount', 'probability'];

/** Local calendar date (toISOString would be UTC and can be "yesterday" for early-morning users east of UTC). */
const today = new Date(Date.now() - new Date().getTimezoneOffset() * 60000).toISOString().slice(0, 10);

const blank = () => ({ ...Object.fromEntries(FIELDS.map((f) => [f, ''])), currency: DEFAULT_CURRENCY });
const fromDeal = (deal) => ({ ...Object.fromEntries(FIELDS.map((f) => [f, deal[f] ?? ''])), currency: deal.currency ?? DEFAULT_CURRENCY });

/** Empty inputs become null so optional fields can be cleared on update; numeric inputs are sent as numbers. */
function toPayload(values) {
    const body = { ...values };
    for (const key of FIELDS) {
        if (!REQUIRED_FIELDS.includes(key) && body[key] === '') body[key] = null;
        if (NUMBER_FIELDS.includes(key) && body[key] !== null) body[key] = Number(body[key]);
    }
    return body;
}

const stepOf = (field) => STEP_FIELDS.findIndex((fields) => fields.includes(field));

/* -------------------------------------------------------------------------- */
/* State                                                                      */
/* -------------------------------------------------------------------------- */

const isEdit = computed(() => props.dealId !== null);
const form = reactive(blank());
const errors = ref({});
const loading = ref(isEdit.value);
const saving = ref(false);
const formEl = ref(null);
const step = ref(0);
const ready = ref(false); // false while the saved deal is being loaded into the form
const isLastStep = computed(() => step.value === STEPS.length - 1);

// Unsaved-changes tracking: the drawer asks before discarding a dirty form.
const serialize = () => JSON.stringify(form);
const snapshot = ref(serialize());
const isDirty = computed(() => serialize() !== snapshot.value);
defineExpose({ isDirty });

const cls = (field) => ({ 'is-invalid': Boolean(errors.value[field]) });

const focusFirstField = () => formEl.value?.querySelector('input:not([type="hidden"])')?.focus();
const focusFirstInvalid = () => formEl.value?.querySelector('.is-invalid')?.focus();

/* -------------------------------------------------------------------------- */
/* Pipeline stage: drives probability, close date and lost reason             */
/* -------------------------------------------------------------------------- */

const createdStages = ref([]); // stages added through "+" before the parent has passed them back down

const stageById = (id) => [...props.stages, ...createdStages.value].find((s) => String(s.id) === String(id)) ?? null;
const currentStage = computed(() => stageById(form.deal_stage_id));
const isClosed = computed(() => (currentStage.value?.outcome ?? 'open') !== 'open');
const isLost = computed(() => currentStage.value?.outcome === 'lost');

/** Mirrors what the server does on a stage change, so the form shows the final values before saving. */
function applyStageDefaults(stage) {
    if (!stage) return;

    form.probability = stageProbability(stage);

    if (stage.outcome === 'open') {
        form.actual_close_date = '';
    } else if (!form.actual_close_date) {
        form.actual_close_date = today;
    }

    if (stage.outcome !== 'lost') form.lost_reason = '';
}

// A stage picked by the user (not the one loaded from the saved deal).
watch(
    () => form.deal_stage_id,
    (id) => ready.value && applyStageDefaults(stageById(id)),
);

/** New deals start in the first open stage of the pipeline. */
function selectDefaultStage() {
    if (isEdit.value || form.deal_stage_id !== '' || !props.stages.length) return;

    const first = props.stages.find((s) => s.outcome === 'open') ?? props.stages[0];
    form.deal_stage_id = first.id;
    applyStageDefaults(first);
    snapshot.value = serialize();
}

// The stage list arrives after the drawer opens, so wait for it.
watch(() => props.stages, selectDefaultStage, { immediate: true });

const weightedPreview = computed(() => {
    if (form.amount === '' || form.probability === '') return null;
    return formatMoney((Number(form.amount) * Number(form.probability)) / 100, form.currency);
});

/* -------------------------------------------------------------------------- */
/* Steps                                                                      */
/* -------------------------------------------------------------------------- */

/** Client-side checks so the user finds mistakes early. The server stays the authority. */
function collectErrors() {
    const found = {};

    if (!form.name.trim()) found.name = 'Deal name is required.';
    if (form.deal_stage_id === '') found.deal_stage_id = 'Select a deal stage.';
    if (form.amount !== '' && !(Number(form.amount) >= 0)) found.amount = 'Enter an amount of 0 or more.';

    const probability = Number(form.probability);
    if (form.probability !== '' && !(Number.isInteger(probability) && probability >= 0 && probability <= 100)) {
        found.probability = 'Enter a whole number from 0 to 100.';
    }

    if (isClosed.value && form.actual_close_date > today) found.actual_close_date = 'The close date cannot be in the future.';
    if (isLost.value && !form.lost_reason.trim()) found.lost_reason = 'Tell us why this deal was lost.';

    return found;
}

/** Validates one step, or the whole form when `index` is null. Returns true when there is nothing to fix. */
function validate(index = null) {
    const found = Object.fromEntries(Object.entries(collectErrors()).filter(([field]) => index === null || stepOf(field) === index));

    errors.value = found;
    return Object.keys(found).length === 0;
}

async function goNext() {
    if (!validate(step.value)) {
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

/** The step header buttons: going back is always allowed, going forward needs a valid current step. */
async function goTo(index) {
    if (index === step.value) return;
    if (index < step.value) return goBack();
    return goNext();
}

/* -------------------------------------------------------------------------- */
/* Company / contact dropdowns (+ "add" opens the create page in a new tab)   */
/* -------------------------------------------------------------------------- */

/** Option list that always keeps the saved record selectable, even if it falls outside the options cap. */
function useOptions(fetchOptions) {
    const items = ref([]);
    const current = ref(null);

    const options = computed(() =>
        current.value && !items.value.some((item) => item.id === current.value.id) ? [current.value, ...items.value] : items.value,
    );

    async function reload() {
        try {
            items.value = await fetchOptions();
        } catch {
            /* the dropdown just stays empty */
        }
    }

    return { options, current, reload };
}

const companies = useOptions(companiesApi.options);
const contacts = useOptions(contactsApi.options);
const reloadRelations = () => Promise.all([companies.reload(), contacts.reload()]);

// Opens in a new tab so this form (and anything typed in it) is never lost; the options refresh on return.
const createCompanyHref = computed(() => router.resolve({ name: 'companies.create' }).href);
const createContactHref = computed(() => router.resolve({ name: 'contacts.create' }).href);

/* -------------------------------------------------------------------------- */
/* Lookups ("Deal Stage +", "Deal Type +", "Lead Source +")                   */
/* -------------------------------------------------------------------------- */

const createStage = (name) => dealLookupsApi.create('deal-stages', name);
const createType = (name) => dealLookupsApi.create('deal-types', name);
// Lead sources are shared with contacts, so creating one here makes it available there too.
const createSource = (name) => contactLookupsApi.create('contact-sources', name);

function onLookupCreated(kind, item) {
    if (kind === 'deal-stages') createdStages.value.push(item);
    emit('lookup-created', { kind, item });
}

/* -------------------------------------------------------------------------- */
/* Load (edit mode)                                                           */
/* -------------------------------------------------------------------------- */

onMounted(async () => {
    window.addEventListener('focus', reloadRelations); // pick up a company / contact created in the other tab
    reloadRelations();

    if (!isEdit.value) {
        ready.value = true;
        return;
    }

    try {
        const deal = await dealsApi.get(props.dealId);
        Object.assign(form, fromDeal(deal));
        companies.current.value = deal.company ?? null;
        contacts.current.value = deal.contact ?? null;
        snapshot.value = serialize();
    } catch {
        toast.error('Could not load the deal.');
        emit('cancel');
    } finally {
        loading.value = false;
        await nextTick(); // let the stage watcher see the loaded value before it starts reacting to user changes
        ready.value = true;
    }
});

onBeforeUnmount(() => window.removeEventListener('focus', reloadRelations));

/* -------------------------------------------------------------------------- */
/* Submit                                                                     */
/* -------------------------------------------------------------------------- */

async function submit(addAnother) {
    if (!validate()) {
        step.value = Math.min(...Object.keys(errors.value).map(stepOf));
        await nextTick(focusFirstInvalid);
        return;
    }

    saving.value = true;
    errors.value = {};

    try {
        const body = toPayload(form);
        const saved = isEdit.value ? await dealsApi.update(props.dealId, body) : await dealsApi.create(body);
        toast.success(isEdit.value ? 'Deal updated.' : 'Deal created.');

        const keepOpen = addAnother && !isEdit.value;
        if (keepOpen) {
            // Keep owner, lead source and currency: they rarely change between consecutive entries.
            const { owner_id, lead_source_id, currency } = form;
            Object.assign(form, blank(), { owner_id, lead_source_id, currency });
            selectDefaultStage();
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

    <form v-else ref="formEl" class="deal-form" novalidate @submit.prevent="onSubmit">
        <!-- Step indicator -->
        <ol class="deal-form__steps" aria-label="Form steps">
            <li v-for="(s, index) in STEPS" :key="s.key" class="deal-form__step-item">
                <button
                    type="button"
                    class="deal-form__step"
                    :class="{ 'is-active': index === step, 'is-done': index < step }"
                    :aria-current="index === step ? 'step' : null"
                    @click="goTo(index)"
                >
                    <span class="deal-form__step-index">
                        <i v-if="index < step" class="bi bi-check-lg" aria-hidden="true"></i>
                        <template v-else>{{ index + 1 }}</template>
                    </span>
                    {{ s.title }}
                </button>
            </li>
        </ol>

        <!-- Step 1: the deal itself -->
        <div v-show="step === 0">
            <section class="deal-form__section">
                <h3 class="deal-form__title"><i class="bi bi-briefcase"></i>Deal information</h3>
                <div class="row g-3">
                    <FormField class="col-12" label="Deal name" required :error="errors.name">
                        <input v-model="form.name" type="text" maxlength="150" placeholder="Website redesign for Acme" class="form-control" :class="cls('name')" />
                    </FormField>

                    <FormField class="col-md-6" label="Deal owner" :error="errors.owner_id">
                        <select v-model="form.owner_id" class="form-select" :class="cls('owner_id')">
                            <option value="">Unassigned</option>
                            <option v-for="o in owners" :key="o.id" :value="o.id">{{ o.name }}</option>
                        </select>
                    </FormField>

                    <FormField class="col-md-6" label="Deal stage" required :error="errors.deal_stage_id">
                        <LookupSelect
                            v-model="form.deal_stage_id"
                            :options="stages"
                            :create="createStage"
                            :invalid="Boolean(errors.deal_stage_id)"
                            placeholder="Select stage"
                            add-label="Add deal stage"
                            add-placeholder="New deal stage"
                            @created="onLookupCreated('deal-stages', $event)"
                        />
                    </FormField>
                </div>
            </section>

            <section class="deal-form__section">
                <h3 class="deal-form__title"><i class="bi bi-cash-coin"></i>Value &amp; forecast</h3>
                <div class="row g-3">
                    <FormField class="col-md-6" label="Amount" :error="errors.amount">
                        <div class="input-group">
                            <select v-model="form.currency" class="form-select deal-form__currency" aria-label="Currency" :class="cls('currency')">
                                <option v-for="code in CURRENCIES" :key="code" :value="code">{{ code }}</option>
                            </select>
                            <input
                                v-model="form.amount"
                                type="number"
                                min="0"
                                step="0.01"
                                inputmode="decimal"
                                placeholder="0.00"
                                class="form-control"
                                :class="cls('amount')"
                            />
                        </div>
                    </FormField>

                    <FormField class="col-md-6" label="Probability (%)" :error="errors.probability">
                        <input
                            v-model="form.probability"
                            type="number"
                            min="0"
                            max="100"
                            step="1"
                            placeholder="0 – 100"
                            class="form-control"
                            :class="cls('probability')"
                            :disabled="isClosed"
                        />
                        <div class="form-text">
                            <template v-if="isClosed">Fixed by the stage ({{ form.probability }}%).</template>
                            <template v-else-if="weightedPreview">Weighted forecast: {{ weightedPreview }}</template>
                            <template v-else>Starts from the stage default.</template>
                        </div>
                    </FormField>

                    <FormField class="col-md-6" label="Expected close date" :error="errors.expected_close_date">
                        <input v-model="form.expected_close_date" type="date" class="form-control" :class="cls('expected_close_date')" />
                    </FormField>

                    <FormField v-if="isClosed" class="col-md-6" label="Closed on" :error="errors.actual_close_date">
                        <input v-model="form.actual_close_date" type="date" :max="today" class="form-control" :class="cls('actual_close_date')" />
                    </FormField>

                    <FormField v-if="isLost" class="col-12" label="Lost reason" required :error="errors.lost_reason">
                        <input
                            v-model="form.lost_reason"
                            type="text"
                            maxlength="255"
                            placeholder="Budget cut, chose a competitor, no response…"
                            class="form-control"
                            :class="cls('lost_reason')"
                        />
                    </FormField>
                </div>
            </section>
        </div>

        <!-- Step 2: who it is with, and the details -->
        <div v-show="step === 1">
            <section class="deal-form__section">
                <h3 class="deal-form__title"><i class="bi bi-people"></i>Relationships</h3>
                <div class="row g-3">
                    <FormField class="col-md-6" label="Company" :error="errors.company_id">
                        <div class="input-group">
                            <select v-model="form.company_id" class="form-select" :class="cls('company_id')">
                                <option value="">No company</option>
                                <option v-for="c in companies.options.value" :key="c.id" :value="c.id">{{ c.name }}</option>
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
                    </FormField>

                    <FormField class="col-md-6" label="Contact" :error="errors.contact_id">
                        <div class="input-group">
                            <select v-model="form.contact_id" class="form-select" :class="cls('contact_id')">
                                <option value="">No contact</option>
                                <option v-for="c in contacts.options.value" :key="c.id" :value="c.id">{{ c.name }}</option>
                            </select>
                            <a
                                :href="createContactHref"
                                target="_blank"
                                rel="noopener"
                                class="btn btn-outline-secondary"
                                title="Add contact"
                                aria-label="Add contact (opens in a new tab)"
                            >
                                <i class="bi bi-plus-lg"></i>
                            </a>
                        </div>
                    </FormField>
                    <div class="col-12 form-text mt-1">Adding a company or contact opens a new tab; the lists refresh when you come back.</div>
                </div>
            </section>

            <section class="deal-form__section">
                <h3 class="deal-form__title"><i class="bi bi-tags"></i>Classification</h3>
                <div class="row g-3">
                    <FormField class="col-md-6" label="Deal type" :error="errors.deal_type_id">
                        <LookupSelect
                            v-model="form.deal_type_id"
                            :options="types"
                            :create="createType"
                            :invalid="Boolean(errors.deal_type_id)"
                            placeholder="Select type"
                            add-label="Add deal type"
                            add-placeholder="New deal type"
                            @created="onLookupCreated('deal-types', $event)"
                        />
                    </FormField>

                    <FormField class="col-md-6" label="Lead source" :error="errors.lead_source_id">
                        <LookupSelect
                            v-model="form.lead_source_id"
                            :options="sources"
                            :create="createSource"
                            :invalid="Boolean(errors.lead_source_id)"
                            placeholder="Select source"
                            add-label="Add lead source"
                            add-placeholder="New lead source"
                            @created="onLookupCreated('contact-sources', $event)"
                        />
                    </FormField>

                    <FormField class="col-md-6" label="Priority" :error="errors.priority">
                        <select v-model="form.priority" class="form-select" :class="cls('priority')">
                            <option value="">Not set</option>
                            <option v-for="p in PRIORITIES" :key="p.value" :value="p.value">{{ p.label }}</option>
                        </select>
                    </FormField>

                    <FormField class="col-md-6" label="Next step" :error="errors.next_step">
                        <input v-model="form.next_step" type="text" maxlength="255" placeholder="Send proposal by Friday" class="form-control" :class="cls('next_step')" />
                    </FormField>
                </div>
            </section>

            <section class="deal-form__section">
                <h3 class="deal-form__title"><i class="bi bi-journal-text"></i>Description</h3>
                <FormField label="Description" :error="errors.description">
                    <textarea
                        v-model="form.description"
                        rows="4"
                        maxlength="5000"
                        placeholder="Scope, requirements, anything worth remembering about this deal…"
                        class="form-control"
                        :class="cls('description')"
                    ></textarea>
                </FormField>
            </section>
        </div>

        <!-- Actions -->
        <div class="deal-form__actions">
            <template v-if="!isLastStep">
                <button type="submit" class="btn btn-primary">Next<i class="bi bi-arrow-right ms-2"></i></button>
            </template>

            <template v-else>
                <div class="deal-form__nav">
                    <button type="button" class="btn btn-outline-secondary" :disabled="saving" @click="goBack">
                        <i class="bi bi-arrow-left me-2"></i>Back
                    </button>
                    <button type="submit" class="btn btn-primary flex-grow-1" :disabled="saving">
                        <span v-if="saving" class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>
                        {{ isEdit ? 'Save Changes' : 'Create Deal' }}
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
.deal-form {
    min-width: 0;
}

.deal-form__section + .deal-form__section {
    padding-top: 1.5rem;
    margin-top: 1.5rem;
    border-top: 1px solid var(--crm-border, #e4e7ec);
}

.deal-form__title {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin: 0 0 1rem;
    font-size: var(--crm-fs-sm, 0.8125rem);
    font-weight: 600;
    color: var(--crm-text-strong, #344054);
}

.deal-form__title i {
    font-size: 1rem;
    color: var(--crm-text-muted, #667085);
}

.deal-form__actions {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    margin-top: 1.75rem;
}

.deal-form__actions .btn-primary {
    min-height: 2.75rem;
}

/* Standard control size, matching the rest of the app */
.deal-form :deep(.form-control),
.deal-form :deep(.form-select) {
    min-height: var(--crm-control-h, 2.25rem);
    font-size: var(--crm-fs, 0.875rem);
    background-color: var(--crm-bg, #f9fafb);
}

.deal-form :deep(.form-control:focus),
.deal-form :deep(.form-select:focus) {
    background-color: #fff;
}

.deal-form :deep(.form-control:disabled) {
    color: var(--crm-text-muted, #667085);
    background-color: var(--crm-hover, #f2f4f7);
}

.deal-form :deep(textarea.form-control) {
    min-height: 6rem;
    resize: vertical;
}

.deal-form :deep(.form-label) {
    margin-bottom: 0.25rem;
    font-size: var(--crm-fs-sm, 0.8125rem);
    font-weight: 500;
    color: var(--crm-text-strong, #344054);
}

.deal-form :deep(.form-text) {
    font-size: var(--crm-fs-xs, 0.75rem);
}

.deal-form :deep(.input-group-text) {
    color: var(--crm-text-muted, #667085);
    background-color: var(--crm-hover, #f2f4f7);
}

.deal-form :deep(.btn) {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: var(--crm-control-h, 2.25rem);
    font-size: var(--crm-fs, 0.875rem);
    font-weight: 500;
}

.deal-form__currency {
    flex: 0 0 5.5rem;
}

/* Step indicator */
.deal-form__steps {
    display: flex;
    gap: 0.75rem;
    padding: 0;
    margin: 0 0 1.5rem;
    list-style: none;
}

.deal-form__step-item {
    flex: 1 1 0;
    min-width: 0;
}

.deal-form__step {
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

.deal-form__step.is-active {
    color: var(--crm-primary);
    background: var(--crm-primary-soft);
    border-color: var(--crm-primary);
}

.deal-form__step.is-done {
    color: var(--crm-text-strong, #344054);
}

.deal-form__step-index {
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

.deal-form__step.is-active .deal-form__step-index {
    color: #fff;
    background: var(--crm-primary);
}

.deal-form__nav {
    display: flex;
    gap: 0.5rem;
}
</style>
