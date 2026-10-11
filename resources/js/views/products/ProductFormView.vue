<script setup>
import { computed, nextTick, onMounted, reactive, ref } from 'vue';
import { productsApi } from '@/api/products';
import { useToastStore } from '@/stores/toast';
import { parseApiError } from '@/utils/errors';
import { formatMoney } from '@/utils/money';
import { CURRENCIES, DEFAULT_CURRENCY, PRODUCT_TYPES, marginPercent } from '@/utils/productOptions';
import FormField from '@/components/FormField.vue';
import LoadingBlock from '@/components/LoadingBlock.vue';

const props = defineProps({
    /** null = create a new product, otherwise edit this product. */
    productId: { type: [String, Number], default: null },
    categories: { type: Array, default: () => [] },
    brands: { type: Array, default: () => [] },
});
const emit = defineEmits(['saved', 'cancel']);

const toast = useToastStore();

/* -------------------------------------------------------------------------- */
/* Field configuration                                                        */
/* -------------------------------------------------------------------------- */

const UNITS = ['each', 'piece', 'box', 'pack', 'kg', 'g', 'lb', 'liter', 'meter', 'hour', 'day', 'month', 'year'];

const STEPS = [
    { key: 'product', title: 'Product information' },
    { key: 'pricing', title: 'Pricing & inventory' },
];

// Which fields live on which step (used for validation and to jump to the step with a server error).
const STEP_FIELDS = [
    ['name', 'sku', 'barcode', 'product_type', 'category_id', 'brand', 'short_description', 'description', 'image_url', 'website_url', 'tags'],
    ['currency', 'sale_price', 'cost_price', 'tax_rate', 'unit_of_measure', 'track_inventory', 'quantity_on_hand', 'reorder_level', 'weight', 'weight_unit', 'dimensions', 'is_active'],
];

/** Server keys can be nested ("dimensions.length", "tags.0"), so match on the root field too. */
const stepOf = (field) => STEP_FIELDS.findIndex((fields) => fields.some((f) => field === f || field.startsWith(`${f}.`)));

const blank = () => ({
    name: '',
    sku: '',
    barcode: '',
    product_type: 'physical',
    category_id: '',
    brand: '',
    short_description: '',
    description: '',
    image_url: '',
    website_url: '',
    tags: '',
    currency: DEFAULT_CURRENCY,
    sale_price: '',
    cost_price: '',
    tax_rate: '0',
    unit_of_measure: 'each',
    track_inventory: false,
    quantity_on_hand: '0',
    reorder_level: '0',
    weight: '',
    weight_unit: 'kg',
    dimensions: { length: '', width: '', height: '', unit: 'cm' },
    is_active: true,
});

const str = (value, fallback = '') => (value === null || value === undefined ? fallback : String(value));

function fromProduct(p) {
    const base = blank();
    return {
        ...base,
        name: str(p.name),
        sku: str(p.sku),
        barcode: str(p.barcode),
        product_type: p.product_type ?? base.product_type,
        category_id: p.category_id ? String(p.category_id) : '',
        brand: str(p.brand),
        short_description: str(p.short_description),
        description: str(p.description),
        image_url: str(p.image_url),
        website_url: str(p.website_url),
        tags: (p.tags ?? []).join(', '),
        currency: p.currency ?? base.currency,
        sale_price: str(p.sale_price),
        cost_price: str(p.cost_price),
        tax_rate: str(p.tax_rate, '0'),
        unit_of_measure: p.unit_of_measure ?? base.unit_of_measure,
        track_inventory: Boolean(p.track_inventory),
        quantity_on_hand: str(p.quantity_on_hand, '0'),
        reorder_level: str(p.reorder_level, '0'),
        weight: str(p.weight),
        weight_unit: p.weight_unit ?? base.weight_unit,
        dimensions: { ...base.dimensions, ...Object.fromEntries(Object.entries(p.dimensions ?? {}).map(([k, v]) => [k, str(v)])) },
        is_active: Boolean(p.is_active),
    };
}

/** Empty optional inputs become null so they can be cleared on update; "sometimes" numeric fields are omitted when blank. */
function toPayload(values) {
    const nullable = ['barcode', 'brand', 'short_description', 'description', 'image_url', 'website_url', 'weight'];
    const omitWhenBlank = ['tax_rate', 'quantity_on_hand', 'reorder_level', 'weight_unit'];

    const body = { ...values };
    for (const key of nullable) if (body[key] === '') body[key] = null;
    for (const key of omitWhenBlank) if (body[key] === '') delete body[key];

    body.category_id = body.category_id === '' ? null : Number(body.category_id);
    body.currency = body.currency.trim().toUpperCase();
    body.tags = values.tags.split(',').map((t) => t.trim()).filter(Boolean);

    const d = values.dimensions;
    const anyDimension = ['length', 'width', 'height'].some((k) => d[k] !== '');
    body.dimensions = anyDimension
        ? Object.fromEntries(Object.entries(d).map(([k, v]) => [k, v === '' ? null : k === 'unit' ? v : Number(v)]))
        : null;

    return body;
}

/* -------------------------------------------------------------------------- */
/* State                                                                      */
/* -------------------------------------------------------------------------- */

const isEdit = computed(() => props.productId !== null);
const form = reactive(blank());
const errors = ref({});
const loading = ref(isEdit.value);
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

const marginPreview = computed(() => {
    const margin = marginPercent(form.sale_price, form.cost_price);
    if (margin === null || form.cost_price === '') return null;
    return `${margin.toFixed(1)}% margin (${formatMoney(Number(form.sale_price) - Number(form.cost_price), form.currency)} per unit)`;
});

/* -------------------------------------------------------------------------- */
/* Steps                                                                      */
/* -------------------------------------------------------------------------- */

const isMoney = (value) => value !== '' && Number(value) >= 0;
const isUrl = (value) => /^https?:\/\/\S+$/i.test(value);

/** Client-side checks so the user finds mistakes early. The server stays the authority. */
function collectErrors() {
    const found = {};

    if (!form.name.trim()) found.name = 'Product name is required.';
    if (!form.sku.trim()) found.sku = 'SKU is required.';
    if (form.image_url && !isUrl(form.image_url)) found.image_url = 'Enter a full link starting with http:// or https://.';
    if (form.website_url && !isUrl(form.website_url)) found.website_url = 'Enter a full link starting with http:// or https://.';

    if (!/^[A-Za-z]{3}$/.test(form.currency.trim())) found.currency = 'Use a 3-letter currency code.';
    if (!isMoney(form.sale_price)) found.sale_price = 'Enter a sale price of 0 or more.';
    if (!isMoney(form.cost_price)) found.cost_price = 'Enter a cost price of 0 or more.';
    if (form.tax_rate !== '' && !(Number(form.tax_rate) >= 0 && Number(form.tax_rate) <= 100)) found.tax_rate = 'Enter a rate from 0 to 100.';
    if (form.quantity_on_hand !== '' && !(Number(form.quantity_on_hand) >= 0)) found.quantity_on_hand = 'Enter 0 or more.';
    if (form.reorder_level !== '' && !(Number(form.reorder_level) >= 0)) found.reorder_level = 'Enter 0 or more.';
    if (form.weight !== '' && !(Number(form.weight) >= 0)) found.weight = 'Enter 0 or more.';

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
/* Load (edit mode)                                                           */
/* -------------------------------------------------------------------------- */

onMounted(async () => {
    if (!isEdit.value) return;

    try {
        Object.assign(form, fromProduct(await productsApi.get(props.productId)));
        snapshot.value = serialize();
    } catch {
        toast.error('Could not load the product.');
        emit('cancel');
    } finally {
        loading.value = false;
    }
});

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
        const saved = isEdit.value ? await productsApi.update(props.productId, body) : await productsApi.create(body);
        toast.success(isEdit.value ? 'Product updated.' : 'Product created.');

        const keepOpen = addAnother && !isEdit.value;
        if (keepOpen) {
            // Keep type, category, brand and currency: they rarely change between consecutive entries.
            const { product_type, category_id, brand, currency, unit_of_measure } = form;
            Object.assign(form, blank(), { product_type, category_id, brand, currency, unit_of_measure });
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

    <form v-else ref="formEl" class="product-form" novalidate @submit.prevent="onSubmit">
        <!-- Step indicator -->
        <ol class="product-form__steps" aria-label="Form steps">
            <li v-for="(s, index) in STEPS" :key="s.key" class="product-form__step-item">
                <button
                    type="button"
                    class="product-form__step"
                    :class="{ 'is-active': index === step, 'is-done': index < step }"
                    :aria-current="index === step ? 'step' : null"
                    @click="goTo(index)"
                >
                    <span class="product-form__step-index">
                        <i v-if="index < step" class="bi bi-check-lg" aria-hidden="true"></i>
                        <template v-else>{{ index + 1 }}</template>
                    </span>
                    {{ s.title }}
                </button>
            </li>
        </ol>

        <!-- Step 1: the product itself -->
        <div v-show="step === 0">
            <section class="product-form__section">
                <h3 class="product-form__title"><i class="bi bi-box-seam"></i>Product information</h3>
                <div class="row g-3">
                    <FormField class="col-12" label="Product name" required :error="errors.name">
                        <input v-model="form.name" type="text" maxlength="180" placeholder="Wireless keyboard" class="form-control" :class="cls('name')" />
                    </FormField>

                    <FormField class="col-md-6" label="SKU" required :error="errors.sku">
                        <input v-model="form.sku" type="text" maxlength="80" placeholder="KB-1001" class="form-control" :class="cls('sku')" />
                    </FormField>

                    <FormField class="col-md-6" label="Barcode" :error="errors.barcode">
                        <input v-model="form.barcode" type="text" maxlength="120" class="form-control" :class="cls('barcode')" />
                    </FormField>

                    <FormField class="col-md-6" label="Product type" required :error="errors.product_type">
                        <select v-model="form.product_type" class="form-select" :class="cls('product_type')">
                            <option v-for="t in PRODUCT_TYPES" :key="t.value" :value="t.value">{{ t.label }}</option>
                        </select>
                    </FormField>

                    <FormField class="col-md-6" label="Category" :error="errors.category_id">
                        <select v-model="form.category_id" class="form-select" :class="cls('category_id')">
                            <option value="">Uncategorized</option>
                            <option v-for="c in categories" :key="c.id" :value="String(c.id)">{{ c.name }}</option>
                        </select>
                    </FormField>

                    <FormField class="col-md-6" label="Brand" :error="errors.brand">
                        <input v-model="form.brand" type="text" maxlength="120" list="product-brand-options" class="form-control" :class="cls('brand')" />
                        <datalist id="product-brand-options">
                            <option v-for="b in brands" :key="b" :value="b"></option>
                        </datalist>
                    </FormField>

                    <FormField class="col-md-6" label="Tags" :error="errors.tags">
                        <input v-model="form.tags" type="text" placeholder="featured, bestseller" class="form-control" :class="cls('tags')" />
                        <div class="form-text">Separate tags with commas.</div>
                    </FormField>
                </div>
            </section>

            <section class="product-form__section">
                <h3 class="product-form__title"><i class="bi bi-link-45deg"></i>Links</h3>
                <div class="row g-3">
                    <FormField class="col-md-6" label="Image URL" :error="errors.image_url">
                        <input v-model="form.image_url" type="url" placeholder="https://" class="form-control" :class="cls('image_url')" />
                    </FormField>

                    <FormField class="col-md-6" label="Website URL" :error="errors.website_url">
                        <input v-model="form.website_url" type="url" placeholder="https://" class="form-control" :class="cls('website_url')" />
                    </FormField>
                </div>
            </section>

            <section class="product-form__section">
                <h3 class="product-form__title"><i class="bi bi-journal-text"></i>Description</h3>
                <div class="row g-3">
                    <FormField class="col-12" label="Short description" :error="errors.short_description">
                        <textarea v-model="form.short_description" rows="2" maxlength="1000" class="form-control" :class="cls('short_description')"></textarea>
                    </FormField>

                    <FormField class="col-12" label="Description" :error="errors.description">
                        <textarea v-model="form.description" rows="4" maxlength="20000" class="form-control" :class="cls('description')"></textarea>
                    </FormField>
                </div>
            </section>
        </div>

        <!-- Step 2: money, stock, shipping -->
        <div v-show="step === 1">
            <section class="product-form__section">
                <h3 class="product-form__title"><i class="bi bi-cash-coin"></i>Pricing</h3>
                <div class="row g-3">
                    <FormField class="col-md-6" label="Currency" required :error="errors.currency">
                        <select v-model="form.currency" class="form-select" :class="cls('currency')">
                            <option v-for="code in CURRENCIES" :key="code" :value="code">{{ code }}</option>
                        </select>
                    </FormField>

                    <FormField class="col-md-6" label="Tax rate (%)" :error="errors.tax_rate">
                        <input v-model="form.tax_rate" type="number" min="0" max="100" step="0.0001" inputmode="decimal" class="form-control" :class="cls('tax_rate')" />
                    </FormField>

                    <FormField class="col-md-6" label="Sale price" required :error="errors.sale_price">
                        <input v-model="form.sale_price" type="number" min="0" step="0.01" inputmode="decimal" placeholder="0.00" class="form-control" :class="cls('sale_price')" />
                    </FormField>

                    <FormField class="col-md-6" label="Cost price" required :error="errors.cost_price">
                        <input v-model="form.cost_price" type="number" min="0" step="0.01" inputmode="decimal" placeholder="0.00" class="form-control" :class="cls('cost_price')" />
                    </FormField>

                    <div v-if="marginPreview" class="col-12 form-text mt-1">{{ marginPreview }}</div>
                </div>
            </section>

            <section class="product-form__section">
                <h3 class="product-form__title"><i class="bi bi-boxes"></i>Inventory</h3>
                <div class="row g-3">
                    <FormField class="col-md-6" label="Unit of measure" required :error="errors.unit_of_measure">
                        <select v-model="form.unit_of_measure" class="form-select" :class="cls('unit_of_measure')">
                            <option v-for="u in UNITS" :key="u" :value="u">{{ u }}</option>
                        </select>
                    </FormField>

                    <div class="col-md-6 d-flex align-items-end">
                        <div class="form-check form-switch product-form__switch">
                            <input id="product-track" v-model="form.track_inventory" type="checkbox" class="form-check-input" role="switch" />
                            <label for="product-track" class="form-check-label">Track inventory</label>
                        </div>
                    </div>

                    <template v-if="form.track_inventory">
                        <FormField class="col-md-6" label="Quantity on hand" :error="errors.quantity_on_hand">
                            <input v-model="form.quantity_on_hand" type="number" min="0" step="0.001" class="form-control" :class="cls('quantity_on_hand')" />
                        </FormField>

                        <FormField class="col-md-6" label="Reorder level" :error="errors.reorder_level">
                            <input v-model="form.reorder_level" type="number" min="0" step="0.001" class="form-control" :class="cls('reorder_level')" />
                            <div class="form-text">Flagged as low stock at or below this quantity.</div>
                        </FormField>
                    </template>
                </div>
            </section>

            <section class="product-form__section">
                <h3 class="product-form__title"><i class="bi bi-truck"></i>Shipping</h3>
                <div class="row g-3">
                    <FormField class="col-md-6" label="Weight" :error="errors.weight">
                        <div class="input-group">
                            <input v-model="form.weight" type="number" min="0" step="0.001" class="form-control" :class="cls('weight')" />
                            <input v-model="form.weight_unit" type="text" maxlength="12" aria-label="Weight unit" class="form-control product-form__unit" />
                        </div>
                    </FormField>

                    <FormField class="col-md-6" label="Dimensions (L × W × H)" :error="errors['dimensions.length'] || errors['dimensions.width'] || errors['dimensions.height'] || errors.dimensions">
                        <div class="input-group">
                            <input v-model="form.dimensions.length" type="number" min="0" step="0.01" placeholder="L" aria-label="Length" class="form-control" :class="cls('dimensions.length')" />
                            <input v-model="form.dimensions.width" type="number" min="0" step="0.01" placeholder="W" aria-label="Width" class="form-control" :class="cls('dimensions.width')" />
                            <input v-model="form.dimensions.height" type="number" min="0" step="0.01" placeholder="H" aria-label="Height" class="form-control" :class="cls('dimensions.height')" />
                            <input v-model="form.dimensions.unit" type="text" maxlength="12" aria-label="Dimension unit" class="form-control product-form__unit" />
                        </div>
                    </FormField>
                </div>
            </section>

            <section class="product-form__section">
                <h3 class="product-form__title"><i class="bi bi-toggle-on"></i>Status</h3>
                <div class="form-check form-switch product-form__switch">
                    <input id="product-active" v-model="form.is_active" type="checkbox" class="form-check-input" role="switch" />
                    <label for="product-active" class="form-check-label">Active</label>
                </div>
            </section>
        </div>

        <!-- Actions -->
        <div class="product-form__actions">
            <template v-if="!isLastStep">
                <button type="submit" class="btn btn-primary">Next<i class="bi bi-arrow-right ms-2"></i></button>
            </template>

            <template v-else>
                <div class="product-form__nav">
                    <button type="button" class="btn btn-outline-secondary" :disabled="saving" @click="goBack">
                        <i class="bi bi-arrow-left me-2"></i>Back
                    </button>
                    <button type="submit" class="btn btn-primary flex-grow-1" :disabled="saving">
                        <span v-if="saving" class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>
                        {{ isEdit ? 'Save Changes' : 'Create Product' }}
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
.product-form {
    min-width: 0;
}

.product-form__section + .product-form__section {
    padding-top: 1.5rem;
    margin-top: 1.5rem;
    border-top: 1px solid var(--crm-border, #e4e7ec);
}

.product-form__title {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin: 0 0 1rem;
    font-size: var(--crm-fs-sm, 0.8125rem);
    font-weight: 600;
    color: var(--crm-text-strong, #344054);
}

.product-form__title i {
    font-size: 1rem;
    color: var(--crm-text-muted, #667085);
}

.product-form__actions {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    margin-top: 1.75rem;
}

.product-form__actions .btn-primary {
    min-height: 2.75rem;
}

/* Standard control size, matching the rest of the app */
.product-form :deep(.form-control),
.product-form :deep(.form-select) {
    min-height: var(--crm-control-h, 2.25rem);
    font-size: var(--crm-fs, 0.875rem);
    background-color: var(--crm-bg, #f9fafb);
}

.product-form :deep(.form-control:focus),
.product-form :deep(.form-select:focus) {
    background-color: #fff;
}

.product-form :deep(.form-control:disabled) {
    color: var(--crm-text-muted, #667085);
    background-color: var(--crm-hover, #f2f4f7);
}

.product-form :deep(textarea.form-control) {
    min-height: 6rem;
    resize: vertical;
}

.product-form :deep(.form-label) {
    margin-bottom: 0.25rem;
    font-size: var(--crm-fs-sm, 0.8125rem);
    font-weight: 500;
    color: var(--crm-text-strong, #344054);
}

.product-form :deep(.form-text) {
    font-size: var(--crm-fs-xs, 0.75rem);
}

.product-form :deep(.input-group-text) {
    color: var(--crm-text-muted, #667085);
    background-color: var(--crm-hover, #f2f4f7);
}

.product-form :deep(.btn) {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: var(--crm-control-h, 2.25rem);
    font-size: var(--crm-fs, 0.875rem);
    font-weight: 500;
}

.product-form__unit {
    flex: 0 0 4.5rem;
}

.product-form__switch {
    min-height: var(--crm-control-h, 2.25rem);
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.product-form__switch .form-check-input {
    margin: 0;
    float: none;
}

/* Step indicator */
.product-form__steps {
    display: flex;
    gap: 0.75rem;
    padding: 0;
    margin: 0 0 1.5rem;
    list-style: none;
}

.product-form__step-item {
    flex: 1 1 0;
    min-width: 0;
}

.product-form__step {
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

.product-form__step.is-active {
    color: var(--crm-primary);
    background: var(--crm-primary-soft);
    border-color: var(--crm-primary);
}

.product-form__step.is-done {
    color: var(--crm-text-strong, #344054);
}

.product-form__step-index {
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

.product-form__step.is-active .product-form__step-index {
    color: #fff;
    background: var(--crm-primary);
}

.product-form__nav {
    display: flex;
    gap: 0.5rem;
}
</style>
