<script setup>
import { computed, ref } from 'vue';
import { leadsApi } from '@/api/leads';
import { LEAD_SOURCES } from '@/constants';
import { useToastStore } from '@/stores/toast';
import { downloadCsv } from '@/utils/csv';
import { parseApiError } from '@/utils/errors';
import { LEAD_CSV_REQUIRED as REQUIRED_COLUMNS, LEAD_CSV_OPTIONAL as OPTIONAL_COLUMNS } from '@/utils/leadCsv';

const emit = defineEmits(['imported']);

const toast = useToastStore();

/* -------------------------------------------------------------------------- */
/* File contract (the API must accept the same columns)                       */
/* -------------------------------------------------------------------------- */

const MAX_BYTES = 5 * 1024 * 1024;
const MAX_ERRORS_SHOWN = 8;

const REQUIRED_COLUMNS = ['first_name', 'last_name', 'email'];
const OPTIONAL_COLUMNS = ['phone', 'company', 'job_title', 'source', 'score', 'estimated_value', 'currency', 'owner_email', 'notes'];
const sourceValues = LEAD_SOURCES.map((s) => s.value);

function downloadDemo() {
    downloadCsv(
        'leads-demo.csv',
        [...REQUIRED_COLUMNS, ...OPTIONAL_COLUMNS],
        [['Jane', 'Doe', 'jane.doe@example.com', '+1 555 0100', 'Acme Inc.', 'Head of Sales', sourceValues[0] ?? 'website', 60, 5000, 'USD', 'owner@example.com', 'Met at the conference']],
    );
}

/* -------------------------------------------------------------------------- */
/* State                                                                      */
/* -------------------------------------------------------------------------- */

const file = ref(null);
const fileError = ref('');
const dragging = ref(false);
const importing = ref(false);
const result = ref(null);

const shownErrors = computed(() => result.value?.errors?.slice(0, MAX_ERRORS_SHOWN) ?? []);
const hiddenErrorCount = computed(() => Math.max(0, (result.value?.errors?.length ?? 0) - MAX_ERRORS_SHOWN));

function formatBytes(bytes) {
    return bytes < 1024 * 1024 ? `${Math.max(1, Math.round(bytes / 1024))} KB` : `${(bytes / 1024 / 1024).toFixed(1)} MB`;
}

function pick(candidate) {
    fileError.value = '';
    if (!candidate) return;

    if (!/\.csv$/i.test(candidate.name) && candidate.type !== 'text/csv') {
        fileError.value = 'Please choose a .csv file.';
    } else if (candidate.size === 0) {
        fileError.value = 'That file is empty.';
    } else if (candidate.size > MAX_BYTES) {
        fileError.value = `That file is too large (max ${formatBytes(MAX_BYTES)}).`;
    } else {
        file.value = candidate;
    }
}

function onInput(event) {
    pick(event.target.files?.[0]);
    event.target.value = ''; // lets the same file be picked again later
}

function onDrop(event) {
    dragging.value = false;
    pick(event.dataTransfer?.files?.[0]);
}

function onDragLeave(event) {
    if (!event.currentTarget.contains(event.relatedTarget)) dragging.value = false;
}

function reset() {
    file.value = null;
    result.value = null;
    fileError.value = '';
}

async function runImport() {
    importing.value = true;
    fileError.value = '';

    try {
        result.value = await leadsApi.import(file.value);
        file.value = null;
        if (result.value.created) toast.success(`${result.value.created} leads imported.`);
        emit('imported', result.value);
    } catch (e) {
        fileError.value = parseApiError(e).message;
    } finally {
        importing.value = false;
    }
}
</script>

<template>
    <section class="import-card" aria-labelledby="lead-import-title">
        <header class="import-card__header">
            <h3 id="lead-import-title" class="import-card__title">Import From CSV</h3>
        </header>

        <div class="import-card__body">
            <!-- Result -->
            <div v-if="result" class="import-result" role="status">
                <div class="import-result__stats">
                    <div class="import-result__stat is-success">
                        <strong>{{ result.created }}</strong>
                        <span>Leads imported</span>
                    </div>
                    <div class="import-result__stat">
                        <strong>{{ result.skipped }}</strong>
                        <span>Rows skipped</span>
                    </div>
                </div>

                <ul v-if="shownErrors.length" class="import-result__errors">
                    <li v-for="err in shownErrors" :key="`${err.row}-${err.message}`">
                        <strong>Row {{ err.row }}:</strong> {{ err.message }}
                    </li>
                </ul>
                <p v-if="hiddenErrorCount" class="small text-muted mt-2 mb-0">…and {{ hiddenErrorCount }} more rows with errors.</p>

                <button type="button" class="btn btn-outline-secondary w-100 mt-3" @click="reset">Import another file</button>
            </div>

            <!-- Picker -->
            <template v-else>
                <p class="import-card__message" :class="{ 'is-error': fileError }" :role="fileError ? 'alert' : null">
                    {{ fileError || 'Please select a CSV file for uploading' }}
                </p>

                <button type="button" class="btn btn-demo" @click="downloadDemo">
                    <i class="bi bi-download me-2"></i>Demo CSV
                </button>

                <label
                    class="file-picker"
                    :class="{ 'is-dragging': dragging }"
                    @dragover.prevent="dragging = true"
                    @dragleave.prevent="onDragLeave"
                    @drop.prevent="onDrop"
                >
                    <input type="file" class="visually-hidden" accept=".csv,text/csv" @change="onInput" />
                    <span class="file-picker__btn">Choose File</span>
                    <span class="file-picker__name" :class="{ 'is-empty': !file }">
                        {{ file ? `${file.name} · ${formatBytes(file.size)}` : 'No file chosen' }}
                    </span>
                </label>

                <button type="button" class="btn btn-primary crm-btn-cta w-100" :disabled="!file || importing" @click="runImport">
                    <span v-if="importing" class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>
                    {{ importing ? 'Importing…' : 'Import From CSV' }}
                </button>

                <details class="import-help">
                    <summary>File format</summary>
                    <p>The first row must be a header. Columns can be in any order.</p>
                    <p>
                        <span class="import-help__label">Required</span>
                        <code v-for="c in REQUIRED_COLUMNS" :key="c">{{ c }}</code>
                    </p>
                    <p>
                        <span class="import-help__label">Optional</span>
                        <code v-for="c in OPTIONAL_COLUMNS" :key="c">{{ c }}</code>
                    </p>
                    <p class="mb-0">
                        <span class="import-help__label">Sources</span>
                        <code v-for="s in sourceValues" :key="s">{{ s }}</code>
                    </p>
                </details>
            </template>
        </div>
    </section>
</template>

<style scoped>
.import-card {
    overflow: hidden;
    background: var(--crm-bg);
    border: 1px solid var(--crm-border);
    border-radius: var(--crm-radius-lg);
}

.import-card__header {
    padding: 0.875rem 1.25rem;
    border-bottom: 1px solid var(--crm-border);
}

.import-card__title {
    margin: 0;
    font-size: 1rem;
    font-weight: 600;
    color: var(--crm-text);
}

.import-card__body {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 1rem;
    padding: 1.25rem;
}

.import-card__message {
    margin: 0;
    font-size: var(--crm-fs);
    color: var(--crm-text-muted);
    text-align: center;
}

.import-card__message.is-error {
    color: #d92d20;
}

/* Teal "Demo CSV" button, as in the reference */
.btn-demo {
    padding: 0 1.25rem;
    color: #fff;
    background: #14b8a6;
    border: 0;
    border-radius: var(--crm-radius);
}

.btn-demo:hover,
.btn-demo:focus-visible {
    color: #fff;
    background: #0f9f90;
}

/* File chooser */
.file-picker {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    width: min(100%, 26rem);
    padding: 0.5rem;
    cursor: pointer;
    background: #fff;
    border: 1px solid #d0d5dd;
    border-radius: 0.625rem;
    transition: border-color 0.15s, background-color 0.15s;
}

.file-picker:hover,
.file-picker.is-dragging,
.file-picker:focus-within {
    background: var(--crm-primary-soft);
    border-color: var(--crm-primary);
}

.file-picker__btn {
    flex: none;
    padding: 0.4rem 0.875rem;
    font-size: var(--crm-fs);
    font-weight: 500;
    color: var(--crm-primary);
    background: var(--crm-primary-soft);
    border-radius: 0.5rem;
}

.file-picker__name {
    min-width: 0;
    overflow: hidden;
    font-size: var(--crm-fs);
    color: var(--crm-text);
    text-overflow: ellipsis;
    white-space: nowrap;
}

.file-picker__name.is-empty {
    color: var(--crm-text-muted);
}

/* Format help */
.import-help {
    width: 100%;
    font-size: 0.8125rem;
    color: var(--crm-text-strong);
}

.import-help summary {
    font-weight: 500;
    color: var(--crm-text-muted);
    cursor: pointer;
}

.import-help p {
    margin: 0.625rem 0 0;
}

.import-help__label {
    display: inline-block;
    min-width: 4.5rem;
    font-weight: 500;
    color: var(--crm-text-muted);
}

.import-help code {
    display: inline-block;
    padding: 0.05rem 0.35rem;
    margin: 0 0.25rem 0.25rem 0;
    font-size: 0.75rem;
    color: var(--crm-text);
    background: #fff;
    border: 1px solid var(--crm-border);
    border-radius: 0.25rem;
}

/* Result */
.import-result {
    width: 100%;
}

.import-result__stats {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.75rem;
}

.import-result__stat {
    padding: 1rem;
    background: #fff;
    border: 1px solid var(--crm-border);
    border-radius: 0.75rem;
}

.import-result__stat strong {
    display: block;
    font-size: 1.5rem;
    font-weight: 600;
    line-height: 1.2;
    font-variant-numeric: tabular-nums;
}

.import-result__stat span {
    font-size: 0.8125rem;
    color: var(--crm-text-muted);
}

.import-result__stat.is-success strong {
    color: #067647;
}

.import-result__errors {
    padding: 0;
    margin: 1rem 0 0;
    list-style: none;
    background: #fffbfa;
    border: 1px solid #fecdca;
    border-radius: 0.75rem;
}

.import-result__errors li {
    padding: 0.5rem 0.75rem;
    font-size: 0.8125rem;
    color: #b42318;
}

.import-result__errors li + li {
    border-top: 1px solid #fee4e2;
}
</style>