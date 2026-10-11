<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import { companiesApi } from '@/api/companies';
import { contactsApi } from '@/api/contacts';
import { dealsApi } from '@/api/deals';
import { productsApi } from '@/api/products';
import { quotesApi } from '@/api/quotes';
import { useToastStore } from '@/stores/toast';
import { parseApiError } from '@/utils/errors';
import { formatMoney } from '@/utils/money';
import { CURRENCIES, DEFAULT_CURRENCY, STATUSES, previewTotals } from '@/utils/quoteOptions';
import FormField from '@/components/FormField.vue';
import LoadingBlock from '@/components/LoadingBlock.vue';

const props = defineProps({
    /** null = create a new quote, otherwise edit this quote. */
    quoteId: { type: [String, Number], default: null },
    /** Create mode only: start from this deal (company, contact, owner and currency are copied over). */
    prefillDealId: { type: [String, Number], default: null },
    owners: { type: Array, default: () => [] },
});
const emit = defineEmits(['saved', 'cancel']);

const toast = useToastStore();
const router = useRouter();

/* -------------------------------------------------------------------------- */
/* Field configuration                                                        */
/* -------------------------------------------------------------------------- */

const STEPS = [
    { key: 'quote', title: 'Quote information' },
    { key: 'items', title: 'Items & terms' },
];

// Which fields live on which step (used for validation and to jump to the step with a server error).
const STEP_FIELDS = [
    ['subject', 'status', 'owner_id', 'deal_id', 'company_id', 'contact_id', 'issue_date', 'valid_until', 'currency'],
    ['items', 'terms', 'notes'],
];

/** Server keys can be nested ("items.0.name"), so match on the root field too. */
const stepOf = (field) => STEP_FIELDS.findIndex((fields) => fields.some((f) => field === f || field.startsWith(`${f}.`)));

/** Local calendar date (toISOString would be UTC and can be "yesterday" for early-morning users east of UTC). */
const localDate = (offsetDays = 0) => {
    const d = new Date();
    d.setDate(d.getDate() + offsetDays);
    return new Date(d.getTime() - d.getTimezoneOffset() * 60000).toISOString().slice(0, 10);
};

let lineKey = 0;
const blankLine = () => ({ key: ++lineKey, product_id: '', name: '', description: '', unit: '', quantity: '1', unit_price: '', discount_percent: '0', tax_rate: '0' });

const str = (value, fallback = '') => (value === null || value === undefined ? fallback : String(value));

const blank = () => ({
    subject: '',
    status: 'draft',
    owner_id: '',
    deal_id: '',
    company_id: '',
    contact_id: '',
    issue_date: localDate(),
    valid_until: localDate(30),
    currency: DEFAULT_CURRENCY,
    terms: '',
    notes: '',
    items: [blankLine()],
});

function fromQuote(q) {
    return {
        subject: str(q.subject),
        status: q.status ?? 'draft',
        owner_id: str(q.owner_id),
        deal_id: str(q.deal_id),
        company_id: str(q.company_id),
        contact_id: str(q.contact_id),
        issue_date: str(q.issue_date),
        valid_until: str(q.valid_until),
        currency: q.currency ?? DEFAULT_CURRENCY,
        terms: str(q.terms),
        notes: str(q.notes),
        items: (q.items ?? []).map((i) => ({
            key: ++lineKey,
            product_id: str(i.product_id),
            name: str(i.name),
            description: str(i.description),
            unit: str(i.unit),
            quantity: str(i.quantity, '1'),
            unit_price: str(i.unit_price),
            discount_percent: str(i.discount_percent, '0'),
            tax_rate: str(i.tax_rate, '0'),
        })),
    };
}

const idOrNull = (value) => (value === '' ? null : Number(value));
const textOrNull = (value) => (value.trim() === '' ? null : value.trim());

/** Empty inputs become null so optional fields can be cleared on update; numeric inputs are sent as numbers. */
function toPayload(values) {
    return {
        subject: values.subject.trim(),
        status: values.status,
        owner_id: idOrNull(values.owner_id),
        deal_id: idOrNull(values.deal_id),
        company_id: idOrNull(values.company_id),
        contact_id: idOrNull(values.contact_id),
        issue_date: values.issue_date,
        valid_until: values.valid_until === '' ? null : values.valid_until,
        currency: values.currency,
        terms: textOrNull(values.terms),
        notes: textOrNull(values.notes),
        items: values.items.map((i) => ({
            product_id: idOrNull(i.product_id),
            name: i.name.trim(),
            description: textOrNull(i.description),
            unit: textOrNull(i.unit),
            quantity: Number(i.quantity),
            unit_price: Number(i.unit_price),
            discount_percent: Number(i.discount_percent || 0),
            tax_rate: Number(i.tax_rate || 0),
        })),
    };
}

/* -------------------------------------------------------------------------- */
/* State                                                                      */
/* -------------------------------------------------------------------------- */

const isEdit = computed(() => props.quoteId !== null);
const form = reactive(blank());
const errors = ref({});
const loading = ref(isEdit.value || props.prefillDealId !== null);
const saving = ref(false);
const formEl = ref(null);
const step = ref(0);
const isLastStep = computed(() => step.value === STEPS.length - 1);

// Unsaved-changes tracking: the drawer asks before discarding a dirty form.
const serialize = () => JSON.stringify(form);
const snapshot = ref(serialize());
const isDirty = computed(() => serialize() !== snapshot.value);
defineExpose({ isDirty });

const cls = (field) => ({ 'is-invalid': Boolean(errors.value[field]) });

const focusFirstField = () => formEl.value?.querySelector('input:not([type="hidden"])')?.focus();
const focusFirstInvalid = () => formEl.value?.querySelector('.is-invalid')?.focus();

const totals = computed(() => previewTotals(form.items));
const money = (amount) => formatMoney(amount, form.currency);

/* -------------------------------------------------------------------------- */
/* Line items                                                                 */
/* -------------------------------------------------------------------------- */

const products = ref([]);
const productById = (id) => products.value.find((p) => String(p.id) === String(id)) ?? null;

async function loadProducts() {
    try {
        // Active products only; the first 100 by name keeps the dropdown fast. Anything else can be typed as a custom line.
        const response = await productsApi.list({ is_active: '1', per_page: 100, sort: 'name', direction: 'asc' });
        products.value = response.data ?? [];
    } catch {
        /* the product picker just stays empty: lines can still be typed by hand */
    }
}

const addLine = async () => {
    form.items.push(blankLine());
    await nextTick();
    formEl.value?.querySelector('.quote-line:last-of-type input[type="text"]')?.focus();
};

const removeLine = (index) => form.items.length > 1 && form.items.splice(index, 1);

/** Picking a product copies its name, unit, tax and (when priced in this quote's currency) its sale price. */
function onProductPicked(line) {
    const product = productById(line.product_id);
    if (!product) return;

    line.name = product.name;
    line.description = product.short_description ?? '';
    line.unit = product.unit_of_measure ?? '';
    line.tax_rate = String(Number(product.tax_rate ?? 0));
    if (product.currency === form.currency) line.unit_price = String(product.sale_price);
}

/** A product priced in another currency is never converted silently: the user enters the price. */
const currencyMismatch = (line) => {
    const product = productById(line.product_id);
    return product && product.currency !== form.currency ? product.currency : null;
};

/* -------------------------------------------------------------------------- */
/* Deal / company / contact                                                   */
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
const deals = useOptions(async () => (await dealsApi.list({ per_page: 100, sort_by: 'name', sort_dir: 'asc' })).data ?? []);
const reloadRelations = () => Promise.all([companies.reload(), contacts.reload(), deals.reload()]);

// Opens in a new tab so this form (and anything typed in it) is never lost; the options refresh on return.
const createCompanyHref = computed(() => router.resolve({ name: 'companies.create' }).href);
const createContactHref = computed(() => router.resolve({ name: 'contacts.create' }).href);

/** Copies what a deal already knows into the quote. Only fills blanks, so nothing the user typed is overwritten. */
function applyDeal(deal) {
    if (!deal) return;
    if (!form.subject.trim()) form.subject = `Quote for ${deal.name}`;
    if (form.company_id === '' && deal.company_id) form.company_id = String(deal.company_id);
    if (form.contact_id === '' && deal.contact_id) form.contact_id = String(deal.contact_id);
    if (form.owner_id === '' && deal.owner_id) form.owner_id = String(deal.owner_id);
    if (deal.currency && CURRENCIES.includes(deal.currency) && form.items.every((i) => i.unit_price === '')) form.currency = deal.currency;
}

function onDealPicked() {
    applyDeal(deals.options.value.find((d) => String(d.id) === form.deal_id));
}

/* -------------------------------------------------------------------------- */
/* Steps                                                                      */
/* -------------------------------------------------------------------------- */

/** Client-side checks so the user finds mistakes early. The server stays the authority. */
function collectErrors() {
    const found = {};

    if (!form.subject.trim()) found.subject = 'Subject is required.';
    if (!form.issue_date) found.issue_date = 'Issue date is required.';
    if (form.valid_until && form.issue_date && form.valid_until < form.issue_date) found.valid_until = 'Valid until cannot be before the issue date.';

    form.items.forEach((item, i) => {
        if (!item.name.trim()) found[`items.${i}.name`] = 'Enter an item name.';
        if (!(Number(item.quantity) > 0)) found[`items.${i}.quantity`] = 'Quantity must be above 0.';
        if (item.unit_price === '' || !(Number(item.unit_price) >= 0)) found[`items.${i}.unit_price`] = 'Enter a price of 0 or more.';
        if (item.discount_percent !== '' && !(Number(item.discount_percent) >= 0 && Number(item.discount_percent) <= 100)) found[`items.${i}.discount_percent`] = '0 – 100.';
        if (item.tax_rate !== '' && !(Number(item.tax_rate) >= 0 && Number(item.tax_rate) <= 100)) found[`items.${i}.tax_rate`] = '0 – 100.';
    });

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
/* Load                                                                       */
/* -------------------------------------------------------------------------- */

onMounted(async () => {
    window.addEventListener('focus', reloadRelations); // pick up a company / contact created in the other tab
    reloadRelations();
    loadProducts();

    try {
        if (isEdit.value) {
            const quote = await quotesApi.get(props.quoteId);
            Object.assign(form, fromQuote(quote));
            companies.current.value = quote.company ?? null;
            contacts.current.value = quote.contact ?? null;
            deals.current.value = quote.deal ?? null;
        } else if (props.prefillDealId !== null) {
            const deal = await dealsApi.get(props.prefillDealId);
            form.deal_id = String(deal.id);
            deals.current.value = deal;
            applyDeal(deal);
        }
        snapshot.value = serialize();
    } catch {
        toast.error(isEdit.value ? 'Could not load the quote.' : 'Could not load the deal.');
        if (isEdit.value) emit('cancel');
    } finally {
        loading.value = false;
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
        const saved = isEdit.value ? await quotesApi.update(props.quoteId, body) : await quotesApi.create(body);
        toast.success(isEdit.value ? 'Quote updated.' : `Quote ${saved.quote_number} created.`);

        const keepOpen = addAnother && !isEdit.value;
        if (keepOpen) {
            // Keep owner and currency: they rarely change between consecutive entries.
            const { owner_id, currency } = form;
            Object.assign(form, blank(), { owner_id, currency });
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

    <form v-else ref="formEl" class="quote-form" novalidate @submit.prevent="onSubmit">
        <!-- Step indicator -->
        <ol class="quote-form__steps" aria-label="Form steps">
            <li v-for="(s, index) in STEPS" :key="s.key" class="quote-form__step-item">
                <button
                    type="button"
                    class="quote-form__step"
                    :class="{ 'is-active': index === step, 'is-done': index < step }"
                    :aria-current="index === step ? 'step' : null"
                    @click="goTo(index)"
                >
                    <span class="quote-form__step-index">
                        <i v-if="index < step" class="bi bi-check-lg" aria-hidden="true"></i>
                        <template v-else>{{ index + 1 }}</template>
                    </span>
                    {{ s.title }}
                </button>
            </li>
        </ol>

        <!-- Step 1: the quote itself -->
        <div v-show="step === 0">
            <section class="quote-form__section">
                <h3 class="quote-form__title"><i class="bi bi-file-earmark-text"></i>Quote information</h3>
                <div class="row g-3">
                    <FormField class="col-12" label="Subject" required :error="errors.subject">
                        <input v-model="form.subject" type="text" maxlength="150" placeholder="Website redesign proposal" class="form-control" :class="cls('subject')" />
                    </FormField>

                    <FormField class="col-md-6" label="Quote owner" :error="errors.owner_id">
                        <select v-model="form.owner_id" class="form-select" :class="cls('owner_id')">
                            <option value="">Unassigned</option>
                            <option v-for="o in owners" :key="o.id" :value="String(o.id)">{{ o.name }}</option>
                        </select>
                    </FormField>

                    <FormField class="col-md-6" label="Status" :error="errors.status">
                        <select v-model="form.status" class="form-select" :class="cls('status')">
                            <option v-for="s in STATUSES" :key="s.value" :value="s.value">{{ s.label }}</option>
                        </select>
                        <div v-if="!isEdit" class="form-text">Quote numbers (Q-2026-0001…) are issued automatically.</div>
                    </FormField>
                </div>
            </section>

            <section class="quote-form__section">
                <h3 class="quote-form__title"><i class="bi bi-people"></i>Relationships</h3>
                <div class="row g-3">
                    <FormField class="col-12" label="Deal" :error="errors.deal_id">
                        <select v-model="form.deal_id" class="form-select" :class="cls('deal_id')" @change="onDealPicked">
                            <option value="">No deal</option>
                            <option v-for="d in deals.options.value" :key="d.id" :value="String(d.id)">{{ d.name }}</option>
                        </select>
                        <div class="form-text">Picking a deal fills in its company, contact and owner when those are still empty.</div>
                    </FormField>

                    <FormField class="col-md-6" label="Company" :error="errors.company_id">
                        <div class="input-group">
                            <select v-model="form.company_id" class="form-select" :class="cls('company_id')">
                                <option value="">No company</option>
                                <option v-for="c in companies.options.value" :key="c.id" :value="String(c.id)">{{ c.name }}</option>
                            </select>
                            <a :href="createCompanyHref" target="_blank" rel="noopener" class="btn btn-outline-secondary" title="Add company" aria-label="Add company (opens in a new tab)">
                                <i class="bi bi-plus-lg"></i>
                            </a>
                        </div>
                    </FormField>

                    <FormField class="col-md-6" label="Contact" :error="errors.contact_id">
                        <div class="input-group">
                            <select v-model="form.contact_id" class="form-select" :class="cls('contact_id')">
                                <option value="">No contact</option>
                                <option v-for="c in contacts.options.value" :key="c.id" :value="String(c.id)">{{ c.name }}</option>
                            </select>
                            <a :href="createContactHref" target="_blank" rel="noopener" class="btn btn-outline-secondary" title="Add contact" aria-label="Add contact (opens in a new tab)">
                                <i class="bi bi-plus-lg"></i>
                            </a>
                        </div>
                    </FormField>
                    <div class="col-12 form-text mt-1">Adding a company or contact opens a new tab; the lists refresh when you come back.</div>
                </div>
            </section>

            <section class="quote-form__section">
                <h3 class="quote-form__title"><i class="bi bi-calendar-event"></i>Dates &amp; currency</h3>
                <div class="row g-3">
                    <FormField class="col-md-4" label="Issue date" required :error="errors.issue_date">
                        <input v-model="form.issue_date" type="date" class="form-control" :class="cls('issue_date')" />
                    </FormField>

                    <FormField class="col-md-4" label="Valid until" :error="errors.valid_until">
                        <input v-model="form.valid_until" type="date" :min="form.issue_date || null" class="form-control" :class="cls('valid_until')" />
                    </FormField>

                    <FormField class="col-md-4" label="Currency" required :error="errors.currency">
                        <select v-model="form.currency" class="form-select" :class="cls('currency')">
                            <option v-for="code in CURRENCIES" :key="code" :value="code">{{ code }}</option>
                        </select>
                    </FormField>
                </div>
            </section>
        </div>

        <!-- Step 2: line items, totals, terms -->
        <div v-show="step === 1">
            <section class="quote-form__section">
                <h3 class="quote-form__title"><i class="bi bi-list-check"></i>Line items</h3>

                <div v-if="errors.items" class="alert alert-danger py-2">{{ errors.items }}</div>

                <div v-for="(line, index) in form.items" :key="line.key" class="quote-line">
                    <div class="quote-line__head">
                        <span class="quote-line__index">Item {{ index + 1 }}</span>
                        <button
                            type="button"
                            class="btn btn-sm btn-link text-danger px-0"
                            :disabled="form.items.length === 1"
                            :aria-label="`Remove item ${index + 1}`"
                            @click="removeLine(index)"
                        >
                            <i class="bi bi-trash me-1"></i>Remove
                        </button>
                    </div>

                    <div class="row g-2">
                        <FormField class="col-12" label="Product">
                            <select v-model="line.product_id" class="form-select" @change="onProductPicked(line)">
                                <option value="">Custom item</option>
                                <option v-for="p in products" :key="p.id" :value="String(p.id)">{{ p.sku }} · {{ p.name }}</option>
                            </select>
                            <div v-if="currencyMismatch(line)" class="form-text text-warning-emphasis">
                                This product is priced in {{ currencyMismatch(line) }}; enter the price in {{ form.currency }}.
                            </div>
                        </FormField>

                        <FormField class="col-12" label="Item name" required :error="errors[`items.${index}.name`]">
                            <input v-model="line.name" type="text" maxlength="180" class="form-control" :class="cls(`items.${index}.name`)" />
                        </FormField>

                        <FormField class="col-12" label="Description" :error="errors[`items.${index}.description`]">
                            <input v-model="line.description" type="text" maxlength="2000" class="form-control" :class="cls(`items.${index}.description`)" />
                        </FormField>

                        <FormField class="col-6 col-md-3" label="Quantity" required :error="errors[`items.${index}.quantity`]">
                            <input v-model="line.quantity" type="number" min="0" step="any" inputmode="decimal" class="form-control" :class="cls(`items.${index}.quantity`)" />
                        </FormField>

                        <FormField class="col-6 col-md-3" label="Unit price" required :error="errors[`items.${index}.unit_price`]">
                            <input v-model="line.unit_price" type="number" min="0" step="0.01" inputmode="decimal" placeholder="0.00" class="form-control" :class="cls(`items.${index}.unit_price`)" />
                        </FormField>

                        <FormField class="col-6 col-md-3" label="Discount (%)" :error="errors[`items.${index}.discount_percent`]">
                            <input v-model="line.discount_percent" type="number" min="0" max="100" step="0.01" inputmode="decimal" class="form-control" :class="cls(`items.${index}.discount_percent`)" />
                        </FormField>

                        <FormField class="col-6 col-md-3" label="Tax (%)" :error="errors[`items.${index}.tax_rate`]">
                            <input v-model="line.tax_rate" type="number" min="0" max="100" step="0.01" inputmode="decimal" class="form-control" :class="cls(`items.${index}.tax_rate`)" />
                        </FormField>
                    </div>

                    <div class="quote-line__amount">
                        <span>Amount (before tax)</span>
                        <strong>{{ money(totals.lines[index]?.net ?? 0) }}</strong>
                    </div>
                </div>

                <button type="button" class="btn btn-outline-secondary w-100" @click="addLine">
                    <i class="bi bi-plus-lg me-2"></i>Add item
                </button>

                <dl class="quote-totals">
                    <div><dt>Subtotal</dt><dd>{{ money(totals.subtotal) }}</dd></div>
                    <div><dt>Discount</dt><dd>−{{ money(totals.discount) }}</dd></div>
                    <div><dt>Tax</dt><dd>{{ money(totals.tax) }}</dd></div>
                    <div class="quote-totals__grand"><dt>Total</dt><dd>{{ money(totals.total) }}</dd></div>
                </dl>
            </section>

            <section class="quote-form__section">
                <h3 class="quote-form__title"><i class="bi bi-journal-text"></i>Terms &amp; notes</h3>
                <div class="row g-3">
                    <FormField class="col-12" label="Terms &amp; conditions" :error="errors.terms">
                        <textarea v-model="form.terms" rows="3" maxlength="5000" placeholder="Payment terms, delivery, warranty… (shown to the customer)" class="form-control" :class="cls('terms')"></textarea>
                    </FormField>

                    <FormField class="col-12" label="Internal notes" :error="errors.notes">
                        <textarea v-model="form.notes" rows="3" maxlength="5000" placeholder="Anything worth remembering about this quote…" class="form-control" :class="cls('notes')"></textarea>
                    </FormField>
                </div>
            </section>
        </div>

        <!-- Actions -->
        <div class="quote-form__actions">
            <template v-if="!isLastStep">
                <button type="submit" class="btn btn-primary">Next<i class="bi bi-arrow-right ms-2"></i></button>
            </template>

            <template v-else>
                <div class="quote-form__nav">
                    <button type="button" class="btn btn-outline-secondary" :disabled="saving" @click="goBack">
                        <i class="bi bi-arrow-left me-2"></i>Back
                    </button>
                    <button type="submit" class="btn btn-primary flex-grow-1" :disabled="saving">
                        <span v-if="saving" class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>
                        {{ isEdit ? 'Save Changes' : 'Create Quote' }}
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
.quote-form {
    min-width: 0;
}

.quote-form__section + .quote-form__section {
    padding-top: 1.5rem;
    margin-top: 1.5rem;
    border-top: 1px solid var(--crm-border, #e4e7ec);
}

.quote-form__title {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin: 0 0 1rem;
    font-size: var(--crm-fs-sm, 0.8125rem);
    font-weight: 600;
    color: var(--crm-text-strong, #344054);
}

.quote-form__title i {
    font-size: 1rem;
    color: var(--crm-text-muted, #667085);
}

.quote-form__actions {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    margin-top: 1.75rem;
}

.quote-form__actions .btn-primary {
    min-height: 2.75rem;
}

/* Standard control size, matching the rest of the app */
.quote-form :deep(.form-control),
.quote-form :deep(.form-select) {
    min-height: var(--crm-control-h, 2.25rem);
    font-size: var(--crm-fs, 0.875rem);
    background-color: var(--crm-bg, #f9fafb);
}

.quote-form :deep(.form-control:focus),
.quote-form :deep(.form-select:focus) {
    background-color: #fff;
}

.quote-form :deep(.form-control:disabled) {
    color: var(--crm-text-muted, #667085);
    background-color: var(--crm-hover, #f2f4f7);
}

.quote-form :deep(textarea.form-control) {
    min-height: 6rem;
    resize: vertical;
}

.quote-form :deep(.form-label) {
    margin-bottom: 0.25rem;
    font-size: var(--crm-fs-sm, 0.8125rem);
    font-weight: 500;
    color: var(--crm-text-strong, #344054);
}

.quote-form :deep(.form-text) {
    font-size: var(--crm-fs-xs, 0.75rem);
}

.quote-form :deep(.input-group-text) {
    color: var(--crm-text-muted, #667085);
    background-color: var(--crm-hover, #f2f4f7);
}

.quote-form :deep(.btn) {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: var(--crm-control-h, 2.25rem);
    font-size: var(--crm-fs, 0.875rem);
    font-weight: 500;
}

.quote-line {
    padding: 1rem;
    margin-bottom: 0.75rem;
    background: var(--crm-surface, #fff);
    border: 1px solid var(--crm-border, #e4e7ec);
    border-radius: var(--crm-radius, 0.5rem);
}

.quote-line__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 0.5rem;
}

.quote-line__index {
    font-size: var(--crm-fs-sm, 0.8125rem);
    font-weight: 600;
    color: var(--crm-text-strong, #344054);
}

.quote-line__amount {
    display: flex;
    justify-content: space-between;
    gap: 1rem;
    padding-top: 0.75rem;
    margin-top: 0.75rem;
    font-size: var(--crm-fs-sm, 0.8125rem);
    color: var(--crm-text-muted, #667085);
    border-top: 1px dashed var(--crm-border, #e4e7ec);
}

.quote-line__amount strong {
    font-size: var(--crm-fs, 0.875rem);
    font-variant-numeric: tabular-nums;
    color: var(--crm-text-strong, #344054);
}

.quote-totals {
    display: flex;
    flex-direction: column;
    gap: 0.375rem;
    padding: 1rem;
    margin: 1rem 0 0;
    font-variant-numeric: tabular-nums;
    background: var(--crm-hover, #f2f4f7);
    border-radius: var(--crm-radius, 0.5rem);
}

.quote-totals div {
    display: flex;
    justify-content: space-between;
    gap: 1rem;
}

.quote-totals dt {
    font-weight: 400;
    color: var(--crm-text-muted, #667085);
}

.quote-totals dd {
    margin: 0;
    font-weight: 500;
}

.quote-totals__grand {
    padding-top: 0.5rem;
    margin-top: 0.25rem;
    border-top: 1px solid var(--crm-border, #e4e7ec);
}

.quote-totals__grand dt,
.quote-totals__grand dd {
    font-size: 1.125rem;
    font-weight: 600;
    color: var(--crm-text-strong, #344054);
}

/* Step indicator */
.quote-form__steps {
    display: flex;
    gap: 0.75rem;
    padding: 0;
    margin: 0 0 1.5rem;
    list-style: none;
}

.quote-form__step-item {
    flex: 1 1 0;
    min-width: 0;
}

.quote-form__step {
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

.quote-form__step.is-active {
    color: var(--crm-primary);
    background: var(--crm-primary-soft);
    border-color: var(--crm-primary);
}

.quote-form__step.is-done {
    color: var(--crm-text-strong, #344054);
}

.quote-form__step-index {
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

.quote-form__step.is-active .quote-form__step-index {
    color: #fff;
    background: var(--crm-primary);
}

.quote-form__nav {
    display: flex;
    gap: 0.5rem;
}
</style>
