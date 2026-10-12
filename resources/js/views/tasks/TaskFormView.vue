<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import { tasksApi } from '@/api/tasks';
import { usePermission } from '@/composables/usePermission';
import { useToastStore } from '@/stores/toast';
import { parseApiError } from '@/utils/errors';
import { OPTION_COLORS } from '@/utils/taskOptions';
import LoadingBlock from '@/components/LoadingBlock.vue';

const props = defineProps({
    /** null = create a new task, otherwise edit (or just view) this task. */
    taskId: { type: [String, Number], default: null },
    /** View-only: same form, every control disabled, no save button. */
    readonly: { type: Boolean, default: false },
});
const emit = defineEmits(['saved', 'cancel']);

const toast = useToastStore();
const router = useRouter();
const { can } = usePermission();

/**
 * Route names of your existing "create" screens, used by the "+" beside Company / Contact / Opportunity / Quote.
 * They open in a new tab (so this half-filled form is not lost) and the dropdowns refresh when you come back.
 * Change these to match your router.
 */
const QUICK_ADD_ROUTES = {
    company: { route: 'companies.create', permission: 'companies.create' },
    contact: { route: 'contacts.create', permission: 'contacts.create' },
    opportunity: { route: 'opportunities.create', permission: 'deals.create' },
    quote: { route: 'quotes.create', permission: 'deals.create' },
};

const isEdit = computed(() => props.taskId !== null);
const EMPTY = () => ({
    name: '',
    assignee_id: '',
    company_id: '',
    contact_id: '',
    opportunity_id: '',
    quote_id: '',
    task_type_id: '',
    task_priority_id: '',
    task_status_id: '',
    due_date: '',
    description: '',
});

const form = reactive(EMPTY());
const lookups = ref({ users: [], companies: [], contacts: [], opportunities: [], quotes: [], types: [], priorities: [], statuses: [], defaults: {} });
const errors = ref({});
const loading = ref(true);
const saving = ref(false);
const formEl = ref(null);

// Unsaved-changes tracking: the drawer asks before discarding a dirty form.
const serialize = () => JSON.stringify(form);
const snapshot = ref(serialize());
const isDirty = computed(() => !props.readonly && serialize() !== snapshot.value);
defineExpose({ isDirty });

const cls = (field) => ({ 'is-invalid': Boolean(errors.value[field]) });
const focusFirstInvalid = () => formEl.value?.querySelector('.is-invalid')?.focus();
const toId = (v) => (v === '' || v === null ? null : Number(v));

/* -------------------------------------------------------------------------- */
/* Load                                                                       */
/* -------------------------------------------------------------------------- */

async function loadLookups() {
    lookups.value = await tasksApi.lookups();
}

onMounted(async () => {
    try {
        const [, task] = await Promise.all([loadLookups(), isEdit.value ? tasksApi.get(props.taskId) : null]);

        if (task) {
            Object.assign(form, {
                name: task.name,
                assignee_id: task.assignee_id ?? '',
                company_id: task.company_id ?? '',
                contact_id: task.contact_id ?? '',
                opportunity_id: task.opportunity_id ?? '',
                quote_id: task.quote_id ?? '',
                task_type_id: task.task_type_id ?? '',
                task_priority_id: task.task_priority_id ?? '',
                task_status_id: task.task_status_id ?? '',
                due_date: task.due_date ?? '',
                description: task.description ?? '',
            });
        } else {
            // New task: assigned to you, with the default status and priority already chosen.
            const d = lookups.value.defaults ?? {};
            form.assignee_id = d.assignee_id ?? '';
            form.task_status_id = d.task_status_id ?? '';
            form.task_priority_id = d.task_priority_id ?? '';
        }
        snapshot.value = serialize();
    } catch {
        toast.error('Could not load the task form.');
        emit('cancel');
    } finally {
        loading.value = false;
    }

    window.addEventListener('focus', onWindowFocus);
});

onBeforeUnmount(() => window.removeEventListener('focus', onWindowFocus));

/* -------------------------------------------------------------------------- */
/* "+" quick add                                                              */
/* -------------------------------------------------------------------------- */

// Task Type / Priority / Status: a small inline form right under the label.
const quick = reactive({ kind: null, name: '', color: OPTION_COLORS[0], saving: false, error: '' });
const LABELS = { type: 'task type', priority: 'task priority', status: 'task status' };
const FIELD_OF = { type: 'task_type_id', priority: 'task_priority_id', status: 'task_status_id' };
const LIST_OF = { type: 'types', priority: 'priorities', status: 'statuses' };

function toggleQuick(kind) {
    if (props.readonly) return;
    Object.assign(quick, { kind: quick.kind === kind ? null : kind, name: '', color: OPTION_COLORS[0], error: '' });
    if (quick.kind) nextTick(() => formEl.value?.querySelector('.quick-add input[type="text"]')?.focus());
}

async function saveQuick() {
    const name = quick.name.trim();
    if (!name) {
        quick.error = 'Name is required.';
        return;
    }

    quick.saving = true;
    quick.error = '';
    try {
        const created = await tasksApi.createOption(quick.kind, { name, color: quick.color });
        lookups.value[LIST_OF[quick.kind]].push(created);
        form[FIELD_OF[quick.kind]] = created.id;
        toast.success(`${created.name} added.`);
        quick.kind = null;
    } catch (e) {
        quick.error = parseApiError(e).fields?.name ?? parseApiError(e).message;
    } finally {
        quick.saving = false;
    }
}

// Company / Contact / Opportunity / Quote: open your existing create screen in a new tab, refresh on return.
let waitingForTab = false;

function openCreateScreen(key) {
    const cfg = QUICK_ADD_ROUTES[key];
    if (!cfg || !router.hasRoute(cfg.route)) {
        toast.error(`Set the "${key}" route name in QUICK_ADD_ROUTES (TaskFormView.vue).`);
        return;
    }
    waitingForTab = true;
    window.open(router.resolve({ name: cfg.route }).href, '_blank', 'noopener');
}

async function onWindowFocus() {
    if (!waitingForTab) return;
    waitingForTab = false;
    try {
        await loadLookups();
        toast.success('Lists refreshed.');
    } catch {
        /* keep the current options */
    }
}

const showPlus = (key) => !props.readonly && can(QUICK_ADD_ROUTES[key].permission);
const showOptionPlus = computed(() => !props.readonly && (can('tasks.create') || can('tasks.edit')));

/* -------------------------------------------------------------------------- */
/* Submit                                                                     */
/* -------------------------------------------------------------------------- */

function validate() {
    const found = {};
    if (!form.name.trim()) found.name = 'Task name is required.';
    errors.value = found;
    return Object.keys(found).length === 0;
}

async function submit() {
    if (props.readonly) return;
    if (!validate()) return nextTick(focusFirstInvalid);

    saving.value = true;
    errors.value = {};

    try {
        const body = {
            name: form.name.trim(),
            assignee_id: toId(form.assignee_id),
            company_id: toId(form.company_id),
            contact_id: toId(form.contact_id),
            opportunity_id: toId(form.opportunity_id),
            quote_id: toId(form.quote_id),
            task_type_id: toId(form.task_type_id),
            task_priority_id: toId(form.task_priority_id),
            task_status_id: toId(form.task_status_id),
            due_date: form.due_date || null,
            description: form.description.trim() || null,
        };

        const saved = isEdit.value ? await tasksApi.update(props.taskId, body) : await tasksApi.create(body);
        toast.success(isEdit.value ? 'Task updated.' : 'Task created.');
        snapshot.value = serialize();
        emit('saved', saved);
    } catch (e) {
        const parsed = parseApiError(e);
        errors.value = parsed.fields;
        toast.error(parsed.message);
        await nextTick(focusFirstInvalid);
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <LoadingBlock v-if="loading" />

    <form v-else ref="formEl" class="task-form" novalidate @submit.prevent="submit">
        <div class="row g-3">
            <!-- Task name -->
            <div class="col-12">
                <label for="task-name" class="form-label"><span class="req" aria-hidden="true">*</span> Task name</label>
                <input id="task-name" v-model="form.name" type="text" maxlength="180" placeholder="Example name" class="form-control" :class="cls('name')" :disabled="readonly" />
                <div v-if="errors.name" class="invalid-feedback d-block">{{ errors.name }}</div>
            </div>

            <!-- Assignee / Company -->
            <div class="col-md-6">
                <label for="task-assignee" class="form-label">Assignee</label>
                <select id="task-assignee" v-model="form.assignee_id" class="form-select" :class="cls('assignee_id')" :disabled="readonly">
                    <option value="">Select assignee</option>
                    <option v-for="u in lookups.users" :key="u.id" :value="u.id">{{ u.name }}</option>
                </select>
                <div v-if="errors.assignee_id" class="invalid-feedback d-block">{{ errors.assignee_id }}</div>
            </div>

            <div class="col-md-6">
                <label for="task-company" class="form-label">
                    Company
                    <button v-if="showPlus('company')" type="button" class="task-plus" aria-label="Add company" @click="openCreateScreen('company')"><i class="bi bi-plus-lg"></i></button>
                </label>
                <select id="task-company" v-model="form.company_id" class="form-select" :class="cls('company_id')" :disabled="readonly">
                    <option value="">Select company name</option>
                    <option v-for="c in lookups.companies" :key="c.id" :value="c.id">{{ c.name }}</option>
                </select>
                <div v-if="errors.company_id" class="invalid-feedback d-block">{{ errors.company_id }}</div>
            </div>

            <!-- Contact -->
            <div class="col-12">
                <label for="task-contact" class="form-label">
                    Contact
                    <button v-if="showPlus('contact')" type="button" class="task-plus" aria-label="Add contact" @click="openCreateScreen('contact')"><i class="bi bi-plus-lg"></i></button>
                </label>
                <select id="task-contact" v-model="form.contact_id" class="form-select" :class="cls('contact_id')" :disabled="readonly">
                    <option value="">Select contact name</option>
                    <option v-for="c in lookups.contacts" :key="c.id" :value="c.id">{{ c.name }}</option>
                </select>
                <div v-if="errors.contact_id" class="invalid-feedback d-block">{{ errors.contact_id }}</div>
            </div>

            <!-- Opportunity / Quote -->
            <div class="col-md-6">
                <label for="task-opportunity" class="form-label">
                    Opportunity
                    <button v-if="showPlus('opportunity')" type="button" class="task-plus" aria-label="Add opportunity" @click="openCreateScreen('opportunity')"><i class="bi bi-plus-lg"></i></button>
                </label>
                <select id="task-opportunity" v-model="form.opportunity_id" class="form-select" :class="cls('opportunity_id')" :disabled="readonly">
                    <option value="">Select opportunity name</option>
                    <option v-for="o in lookups.opportunities" :key="o.id" :value="o.id">{{ o.name }}</option>
                </select>
                <div v-if="errors.opportunity_id" class="invalid-feedback d-block">{{ errors.opportunity_id }}</div>
            </div>

            <div class="col-md-6">
                <label for="task-quote" class="form-label">
                    Quote
                    <button v-if="showPlus('quote')" type="button" class="task-plus" aria-label="Add quote" @click="openCreateScreen('quote')"><i class="bi bi-plus-lg"></i></button>
                </label>
                <select id="task-quote" v-model="form.quote_id" class="form-select" :class="cls('quote_id')" :disabled="readonly">
                    <option value="">Select quote name</option>
                    <option v-for="q in lookups.quotes" :key="q.id" :value="q.id">{{ q.name }}</option>
                </select>
                <div v-if="errors.quote_id" class="invalid-feedback d-block">{{ errors.quote_id }}</div>
            </div>

            <!-- Task type / Task priority -->
            <div class="col-md-6">
                <label for="task-type" class="form-label">
                    Task Type
                    <button v-if="showOptionPlus" type="button" class="task-plus" aria-label="Add task type" :aria-expanded="quick.kind === 'type'" @click="toggleQuick('type')"><i class="bi bi-plus-lg"></i></button>
                </label>
                <select id="task-type" v-model="form.task_type_id" class="form-select" :class="cls('task_type_id')" :disabled="readonly">
                    <option value="">Select task type</option>
                    <option v-for="t in lookups.types" :key="t.id" :value="t.id">{{ t.name }}</option>
                </select>
                <div v-if="errors.task_type_id" class="invalid-feedback d-block">{{ errors.task_type_id }}</div>
            </div>

            <div class="col-md-6">
                <label for="task-priority" class="form-label">
                    Task Priority
                    <button v-if="showOptionPlus" type="button" class="task-plus" aria-label="Add task priority" :aria-expanded="quick.kind === 'priority'" @click="toggleQuick('priority')"><i class="bi bi-plus-lg"></i></button>
                </label>
                <select id="task-priority" v-model="form.task_priority_id" class="form-select" :class="cls('task_priority_id')" :disabled="readonly">
                    <option value="">Select task priority</option>
                    <option v-for="p in lookups.priorities" :key="p.id" :value="p.id">{{ p.name }}</option>
                </select>
                <div v-if="errors.task_priority_id" class="invalid-feedback d-block">{{ errors.task_priority_id }}</div>
            </div>

            <!-- Task status -->
            <div class="col-12">
                <label for="task-status" class="form-label">
                    Task Status
                    <button v-if="showOptionPlus" type="button" class="task-plus" aria-label="Add task status" :aria-expanded="quick.kind === 'status'" @click="toggleQuick('status')"><i class="bi bi-plus-lg"></i></button>
                </label>
                <select id="task-status" v-model="form.task_status_id" class="form-select" :class="cls('task_status_id')" :disabled="readonly">
                    <option value="">Select task status</option>
                    <option v-for="s in lookups.statuses" :key="s.id" :value="s.id">{{ s.name }}</option>
                </select>
                <div v-if="errors.task_status_id" class="invalid-feedback d-block">{{ errors.task_status_id }}</div>
            </div>

            <!-- Inline "add new type / priority / status" (shared by the three "+" buttons above) -->
            <div v-if="quick.kind" class="col-12">
                <div class="quick-add" role="group" :aria-label="`Add ${LABELS[quick.kind]}`">
                    <div class="quick-add__title">New {{ LABELS[quick.kind] }}</div>
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <input v-model="quick.name" type="text" maxlength="80" class="form-control quick-add__input" :class="{ 'is-invalid': quick.error }" :placeholder="`Name of the ${LABELS[quick.kind]}`" @keydown.enter.prevent="saveQuick" />
                        <div class="d-flex gap-1" role="radiogroup" aria-label="Colour">
                            <button
                                v-for="c in OPTION_COLORS"
                                :key="c"
                                type="button"
                                class="quick-add__swatch"
                                :class="{ 'is-active': quick.color === c }"
                                :style="{ backgroundColor: c }"
                                role="radio"
                                :aria-checked="quick.color === c"
                                :aria-label="c"
                                @click="quick.color = c"
                            ></button>
                        </div>
                        <button type="button" class="btn btn-primary" :disabled="quick.saving" @click="saveQuick">
                            <span v-if="quick.saving" class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Add
                        </button>
                        <button type="button" class="btn btn-link text-decoration-none" @click="quick.kind = null">Cancel</button>
                    </div>
                    <div v-if="quick.error" class="invalid-feedback d-block">{{ quick.error }}</div>
                </div>
            </div>

            <!-- Due date -->
            <div class="col-12">
                <label for="task-due" class="form-label">Due date</label>
                <input id="task-due" v-model="form.due_date" type="date" class="form-control" :class="cls('due_date')" placeholder="Select due date" :disabled="readonly" />
                <div v-if="errors.due_date" class="invalid-feedback d-block">{{ errors.due_date }}</div>
            </div>

            <!-- Description -->
            <div class="col-12">
                <label for="task-description" class="form-label">Description</label>
                <textarea id="task-description" v-model="form.description" rows="4" maxlength="20000" placeholder="Descriptions" class="form-control" :class="cls('description')" :disabled="readonly"></textarea>
                <div v-if="errors.description" class="invalid-feedback d-block">{{ errors.description }}</div>
            </div>
        </div>

        <div v-if="!readonly" class="task-form__actions">
            <button type="submit" class="btn btn-primary" :disabled="saving">
                <span v-if="saving" class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>
                {{ isEdit ? 'Save Changes' : 'Create' }}
            </button>
        </div>
        <div v-else class="task-form__actions">
            <button type="button" class="btn btn-outline-secondary" @click="emit('cancel')">Close</button>
        </div>
    </form>
</template>

<style scoped>
/* Sizes, colours and fonts come from the global --crm-* tokens, same as the Roles and Products forms. */
.task-form {
    min-width: 0;
    font-family: var(--crm-font);
}

.task-form__actions {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    margin-top: 1.75rem;
}

.task-form__actions .btn-primary {
    min-height: 2.75rem;
}

.task-form :deep(.form-control),
.task-form :deep(.form-select) {
    min-height: var(--crm-control-h, 2.25rem);
    font-size: var(--crm-fs, 0.875rem);
    background-color: var(--crm-bg, #f9fafb);
}

.task-form :deep(.form-control:focus),
.task-form :deep(.form-select:focus) {
    background-color: #fff;
}

.task-form :deep(.form-control:disabled),
.task-form :deep(.form-select:disabled) {
    color: var(--crm-text-muted, #667085);
    background-color: var(--crm-hover, #f2f4f7);
}

.task-form :deep(textarea.form-control) {
    min-height: 6rem;
    resize: vertical;
}

.task-form :deep(.form-label) {
    display: flex;
    align-items: center;
    gap: 0.375rem;
    margin-bottom: 0.25rem;
    font-size: var(--crm-fs-sm, 0.8125rem);
    font-weight: 500;
    color: var(--crm-text-strong, #344054);
}

.task-form :deep(.btn) {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: var(--crm-control-h, 2.25rem);
    font-size: var(--crm-fs, 0.875rem);
    font-weight: 500;
}

.req {
    color: var(--bs-danger, #dc3545);
}

/* The small blue "+" beside a label */
.task-plus {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 1.25rem;
    height: 1.25rem;
    padding: 0;
    font-size: 0.75rem;
    line-height: 1;
    color: #fff;
    background: var(--bs-primary);
    border: 0;
    border-radius: 0.3rem;
}

.task-plus:hover {
    filter: brightness(0.92);
}

.task-plus:focus-visible {
    outline: 2px solid var(--bs-primary);
    outline-offset: 2px;
}

/* Inline add panel */
.quick-add {
    padding: 0.75rem;
    background: var(--crm-hover, #f2f4f7);
    border: 1px solid var(--crm-border, #e4e7ec);
    border-radius: var(--crm-radius, 0.5rem);
}

.quick-add__title {
    margin-bottom: 0.5rem;
    font-size: var(--crm-fs-sm, 0.8125rem);
    font-weight: 600;
    color: var(--crm-text-strong, #344054);
}

.quick-add__input {
    flex: 1 1 11rem;
    min-width: 0;
    background-color: #fff !important;
}

.quick-add__swatch {
    width: 1.25rem;
    height: 1.25rem;
    padding: 0;
    border: 2px solid transparent;
    border-radius: 50%;
}

.quick-add__swatch.is-active {
    outline: 2px solid var(--crm-text-strong, #344054);
    outline-offset: 1px;
}
</style>
