<script setup>
import { computed, nextTick, onMounted, reactive, ref } from 'vue';
import { rolesApi } from '@/api/access';
import { useToastStore } from '@/stores/toast';
import { parseApiError } from '@/utils/errors';
import FormField from '@/components/FormField.vue';
import LoadingBlock from '@/components/LoadingBlock.vue';
import PermissionMatrix from '@/components/PermissionMatrix.vue';

const props = defineProps({
    /** null = create a new role, otherwise edit (or just view) this role. */
    roleId: { type: [String, Number], default: null },
    /** Permission matrix from GET /permissions, loaded once by the list page. */
    groups: { type: Array, default: () => [] },
    /** View-only: the same form with every control disabled and no save button. */
    readonly: { type: Boolean, default: false },
});
const emit = defineEmits(['saved', 'cancel']);

const toast = useToastStore();

const isEdit = computed(() => props.roleId !== null);
const form = reactive({ name: '', description: '', permission_ids: [] });
const role = ref(null);
const errors = ref({});
const loading = ref(isEdit.value);
const saving = ref(false);
const formEl = ref(null);

const isSystem = computed(() => Boolean(role.value?.is_system));
const isSuperAdmin = computed(() => Boolean(role.value?.is_super_admin));

// Unsaved-changes tracking: the drawer asks before discarding a dirty form.
const serialize = () => JSON.stringify({ ...form, permission_ids: [...form.permission_ids].sort((a, b) => a - b) });
const snapshot = ref(serialize());
const isDirty = computed(() => !props.readonly && serialize() !== snapshot.value);
defineExpose({ isDirty });

const cls = (field) => ({ 'is-invalid': Boolean(errors.value[field]) });
const focusFirstInvalid = () => formEl.value?.querySelector('.is-invalid')?.focus();

onMounted(async () => {
    if (!isEdit.value) return;

    try {
        role.value = await rolesApi.get(props.roleId);
        form.name = role.value.name;
        form.description = role.value.description ?? '';
        form.permission_ids = [...(role.value.permission_ids ?? [])];
        snapshot.value = serialize();
    } catch {
        toast.error('Could not load the role.');
        emit('cancel');
    } finally {
        loading.value = false;
    }
});

function validate() {
    const found = {};
    if (!form.name.trim()) found.name = 'Role name is required.';
    errors.value = found;
    return Object.keys(found).length === 0;
}

async function submit(addAnother) {
    if (props.readonly) return;
    if (!validate()) return nextTick(focusFirstInvalid);

    saving.value = true;
    errors.value = {};

    try {
        const body = { name: form.name.trim(), description: form.description.trim() || null, permission_ids: form.permission_ids };
        const saved = isEdit.value ? await rolesApi.update(props.roleId, body) : await rolesApi.create(body);
        toast.success(isEdit.value ? 'Role updated.' : 'Role created.');

        const keepOpen = addAnother && !isEdit.value;
        if (keepOpen) Object.assign(form, { name: '', description: '', permission_ids: [] });
        snapshot.value = serialize();

        emit('saved', saved, { another: keepOpen });
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

    <form v-else ref="formEl" class="role-form" novalidate @submit.prevent="submit(false)">
        <section class="role-form__section">
            <h3 class="role-form__title"><i class="bi bi-shield-check"></i>Role details</h3>
            <div class="row g-3">
                <FormField class="col-12" label="Role name" required :error="errors.name">
                    <input
                        v-model="form.name"
                        type="text"
                        maxlength="80"
                        placeholder="Sales Manager"
                        class="form-control"
                        :class="cls('name')"
                        :disabled="readonly || isSystem"
                    />
                    <div v-if="isSystem" class="form-text">System roles keep their name.</div>
                </FormField>

                <FormField class="col-12" label="Description" :error="errors.description">
                    <textarea
                        v-model="form.description"
                        rows="2"
                        maxlength="255"
                        placeholder="What can people with this role do?"
                        class="form-control"
                        :class="cls('description')"
                        :disabled="readonly"
                    ></textarea>
                </FormField>
            </div>
        </section>

        <section class="role-form__section">
            <h3 class="role-form__title"><i class="bi bi-grid-3x3-gap"></i>Permissions</h3>

            <div v-if="isSuperAdmin" class="alert alert-info py-2 small" role="note">
                <i class="bi bi-info-circle me-2"></i>Super Admin always has every permission and can't be restricted.
            </div>

            <PermissionMatrix v-model="form.permission_ids" :groups="groups" :readonly="readonly" :locked="isSuperAdmin" />
            <div v-if="errors.permission_ids" class="invalid-feedback d-block">{{ errors.permission_ids }}</div>
        </section>

        <div v-if="!readonly" class="role-form__actions">
            <button type="submit" class="btn btn-primary" :disabled="saving">
                <span v-if="saving" class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>
                {{ isEdit ? 'Save Changes' : 'Create Role' }}
            </button>
            <button v-if="!isEdit" type="button" class="btn btn-link text-decoration-none" :disabled="saving" @click="submit(true)">
                Save &amp; add another
            </button>
        </div>
        <div v-else class="role-form__actions">
            <button type="button" class="btn btn-outline-secondary" @click="emit('cancel')">Close</button>
        </div>
    </form>
</template>

<style scoped>
.role-form {
    min-width: 0;
}

.role-form__section + .role-form__section {
    padding-top: 1.5rem;
    margin-top: 1.5rem;
    border-top: 1px solid var(--crm-border, #e4e7ec);
}

.role-form__title {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin: 0 0 1rem;
    font-size: var(--crm-fs-sm, 0.8125rem);
    font-weight: 600;
    color: var(--crm-text-strong, #344054);
}

.role-form__title i {
    font-size: 1rem;
    color: var(--crm-text-muted, #667085);
}

.role-form__actions {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    margin-top: 1.75rem;
}

.role-form__actions .btn-primary {
    min-height: 2.75rem;
}

.role-form :deep(.form-control),
.role-form :deep(.form-select) {
    min-height: var(--crm-control-h, 2.25rem);
    font-size: var(--crm-fs, 0.875rem);
    background-color: var(--crm-bg, #f9fafb);
}

.role-form :deep(.form-control:focus),
.role-form :deep(.form-select:focus) {
    background-color: #fff;
}

.role-form :deep(.form-control:disabled) {
    color: var(--crm-text-muted, #667085);
    background-color: var(--crm-hover, #f2f4f7);
}

.role-form :deep(textarea.form-control) {
    min-height: 4.5rem;
    resize: vertical;
}

.role-form :deep(.form-label) {
    margin-bottom: 0.25rem;
    font-size: var(--crm-fs-sm, 0.8125rem);
    font-weight: 500;
    color: var(--crm-text-strong, #344054);
}

.role-form :deep(.form-text) {
    font-size: var(--crm-fs-xs, 0.75rem);
}

.role-form :deep(.btn) {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: var(--crm-control-h, 2.25rem);
    font-size: var(--crm-fs, 0.875rem);
    font-weight: 500;
}
</style>
