<script setup>
import { computed, nextTick, onMounted, reactive, ref } from 'vue';
import { userAccessApi } from '@/api/access';
import { useToastStore } from '@/stores/toast';
import { parseApiError } from '@/utils/errors';
import FormField from '@/components/FormField.vue';
import LoadingBlock from '@/components/LoadingBlock.vue';
import PermissionMatrix from '@/components/PermissionMatrix.vue';

const props = defineProps({
    /** null = create a new user, otherwise manage this user's access. */
    userId: { type: [String, Number], default: null },
    /** [{ id, name, slug, description, is_system, is_super_admin, permission_ids }] from GET /access/users/lookups */
    roles: { type: Array, default: () => [] },
    /** Permission matrix from GET /permissions, loaded once by the list page. */
    groups: { type: Array, default: () => [] },
});
const emit = defineEmits(['saved', 'cancel']);

const toast = useToastStore();

const isCreate = computed(() => props.userId === null);
const user = ref(null);
const account = reactive({ name: '', email: '', password: '', password_confirmation: '' });
const showPassword = ref(false);
const selected = ref([]);
const extra = ref([]);   // permissions given straight to this user, on top of their roles
const errors = ref({});
const loading = ref(!isCreate.value);
const saving = ref(false);

const sorted = (list) => [...list].sort((a, b) => a - b);
const serialize = () => JSON.stringify({ account: { ...account }, roles: sorted(selected.value), extra: sorted(extra.value) });

// What the ticked roles already grant, so the matrix can show it and only the rest is "extra".
const hasSuperAdmin = computed(() => props.roles.some((r) => selected.value.includes(r.id) && r.is_super_admin));
const inherited = computed(() => [...new Set(props.roles.filter((r) => selected.value.includes(r.id)).flatMap((r) => r.permission_ids ?? []))]);
const effectiveCount = computed(() => new Set([...inherited.value, ...extra.value]).size);
const totalCount = computed(() => props.groups.reduce((n, g) => n + g.permissions.length, 0));
const snapshot = ref(serialize());
const isDirty = computed(() => serialize() !== snapshot.value);
defineExpose({ isDirty });

onMounted(async () => {
    if (isCreate.value) return;

    try {
        user.value = await userAccessApi.get(props.userId);
        selected.value = [...(user.value.role_ids ?? [])];
        extra.value = [...(user.value.permission_ids ?? [])];
        snapshot.value = serialize();
    } catch {
        toast.error('Could not load the user.');
        emit('cancel');
    } finally {
        loading.value = false;
    }
});

function validate() {
    const found = {};
    if (!account.name.trim()) found.name = 'Name is required.';
    if (!account.email.trim()) found.email = 'E-mail is required.';
    if (account.password.length < 8) found.password = 'Use at least 8 characters.';
    else if (account.password !== account.password_confirmation) found.password_confirmation = 'Passwords do not match.';
    errors.value = found;
    return Object.keys(found).length === 0;
}

async function submit() {
    if (isCreate.value && !validate()) {
        await nextTick();
        document.querySelector('.user-roles .is-invalid')?.focus();
        return;
    }

    saving.value = true;
    errors.value = {};

    try {
        if (isCreate.value) {
            const inheritedIds = new Set(inherited.value);
            const created = await userAccessApi.create({
                name: account.name.trim(),
                email: account.email.trim(),
                password: account.password,
                password_confirmation: account.password_confirmation,
                role_ids: selected.value,
                permission_ids: extra.value.filter((id) => !inheritedIds.has(id)),
            });
            snapshot.value = serialize();
            toast.success('User created.');
            emit('saved', created);
            return;
        }

        // Anything a role already grants is not stored as an extra, so removing the role really removes it.
        const inheritedSet = new Set(inherited.value);
        extra.value = extra.value.filter((id) => !inheritedSet.has(id));
        const saved = await userAccessApi.updateRoles(props.userId, selected.value, extra.value);
        snapshot.value = serialize();
        toast.success('Access updated.');
        emit('saved', saved);
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
    <LoadingBlock v-if="loading" />

    <form v-else class="user-roles" novalidate @submit.prevent="submit">
        <div v-if="!isCreate" class="user-roles__user">
            <span class="user-roles__avatar" aria-hidden="true"><i class="bi bi-person"></i></span>
            <div class="min-w-0">
                <strong class="d-block text-truncate">{{ user.name }}</strong>
                <span class="user-roles__email">{{ user.email }}</span>
            </div>
        </div>

        <section v-else class="user-roles__section user-roles__account">
            <h3 class="user-roles__title"><i class="bi bi-person-plus"></i>Account</h3>
            <div class="row g-3">
                <FormField class="col-12" label="Full name" required :error="errors.name">
                    <input v-model="account.name" type="text" maxlength="255" autocomplete="off" placeholder="Rahim Uddin" class="form-control" :class="{ 'is-invalid': errors.name }" />
                </FormField>
                <FormField class="col-12" label="E-mail" required :error="errors.email">
                    <input v-model="account.email" type="email" maxlength="255" autocomplete="off" placeholder="rahim@example.com" class="form-control" :class="{ 'is-invalid': errors.email }" />
                </FormField>
                <FormField class="col-12 col-md-6" label="Password" required :error="errors.password">
                    <input v-model="account.password" :type="showPassword ? 'text' : 'password'" autocomplete="new-password" class="form-control" :class="{ 'is-invalid': errors.password }" />
                    <div class="form-text">At least 8 characters.</div>
                </FormField>
                <FormField class="col-12 col-md-6" label="Confirm password" required :error="errors.password_confirmation">
                    <input v-model="account.password_confirmation" :type="showPassword ? 'text' : 'password'" autocomplete="new-password" class="form-control" :class="{ 'is-invalid': errors.password_confirmation }" />
                </FormField>
                <div class="col-12">
                    <div class="form-check">
                        <input id="user-show-password" v-model="showPassword" type="checkbox" class="form-check-input" />
                        <label for="user-show-password" class="form-check-label">Show password</label>
                    </div>
                </div>
            </div>
        </section>

        <section class="user-roles__section">
            <h3 class="user-roles__title"><i class="bi bi-shield-check"></i>Roles</h3>
            <p class="user-roles__help">A user gets every permission from all of their roles combined.</p>

            <ul class="user-roles__list">
                <li v-for="r in roles" :key="r.id">
                    <label class="user-roles__option" :class="{ 'is-on': selected.includes(r.id) }" :for="`user-role-${r.id}`">
                        <input :id="`user-role-${r.id}`" v-model="selected" type="checkbox" class="form-check-input" :value="r.id" />
                        <span class="min-w-0">
                            <span class="user-roles__name">
                                {{ r.name }}
                                <span v-if="r.is_system" class="badge text-bg-primary ms-1">System</span>
                            </span>
                            <span v-if="r.description" class="user-roles__desc">{{ r.description }}</span>
                        </span>
                    </label>
                </li>
                <li v-if="!roles.length" class="text-muted small">No roles yet. Create one under Roles &amp; Permissions.</li>
            </ul>

            <div v-if="errors.role_ids" class="invalid-feedback d-block">{{ errors.role_ids }}</div>
            <div v-if="!selected.length && !extra.length" class="alert alert-warning py-2 small mt-3 mb-0" role="note">
                <i class="bi bi-exclamation-triangle me-2"></i>With no role or permission, this user can't open any protected page.
            </div>
        </section>

        <section class="user-roles__section user-roles__section--perms">
            <h3 class="user-roles__title"><i class="bi bi-grid-3x3-gap"></i>Permissions</h3>
            <p class="user-roles__help">
                Greyed ticks come from the selected roles. Tick more boxes to give <strong>{{ isCreate ? 'this user' : user.name }}</strong> extra permissions on top of their roles.
            </p>

            <div v-if="hasSuperAdmin" class="alert alert-info py-2 small" role="note">
                <i class="bi bi-info-circle me-2"></i>Super Admin always has every permission and can't be restricted.
            </div>

            <PermissionMatrix v-model="extra" :groups="groups" :inherited="inherited" :locked="hasSuperAdmin" />
            <div v-if="errors.permission_ids" class="invalid-feedback d-block">{{ errors.permission_ids }}</div>
            <p class="user-roles__summary">{{ hasSuperAdmin ? totalCount : effectiveCount }} of {{ totalCount }} permissions in effect for this user.</p>
        </section>

        <div class="user-roles__actions">
            <button type="submit" class="btn btn-primary" :disabled="saving">
                <span v-if="saving" class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>{{ isCreate ? 'Create User' : 'Save Access' }}
            </button>
            <button type="button" class="btn btn-link text-decoration-none" :disabled="saving" @click="emit('cancel')">Cancel</button>
        </div>
    </form>
</template>

<style scoped>
.min-w-0 {
    min-width: 0;
}

.user-roles__user {
    display: flex;
    align-items: center;
    gap: 0.875rem;
    padding: 0.875rem 1rem;
    margin-bottom: 1.5rem;
    background: var(--crm-bg, #f9fafb);
    border: 1px solid var(--crm-border, #e4e7ec);
    border-radius: var(--crm-radius, 0.5rem);
}

.user-roles__avatar {
    display: inline-flex;
    flex: none;
    align-items: center;
    justify-content: center;
    width: 2.5rem;
    height: 2.5rem;
    font-size: 1.25rem;
    color: var(--crm-primary);
    background: var(--crm-primary-soft);
    border-radius: 50%;
}

.user-roles__email {
    font-size: var(--crm-fs-sm, 0.8125rem);
    color: var(--crm-text-muted, #667085);
}

.user-roles__title {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin: 0 0 0.25rem;
    font-size: var(--crm-fs-sm, 0.8125rem);
    font-weight: 600;
    color: var(--crm-text-strong, #344054);
}

.user-roles__title i {
    font-size: 1rem;
    color: var(--crm-text-muted, #667085);
}

.user-roles__help {
    margin: 0 0 1rem;
    font-size: var(--crm-fs-xs, 0.75rem);
    color: var(--crm-text-muted, #667085);
}

.user-roles__account {
    margin-bottom: 1.5rem;
}

.user-roles__section--perms {
    margin-top: 1.5rem;
}

.user-roles__summary {
    margin: 0.75rem 0 0;
    font-size: var(--crm-fs-sm, 0.8125rem);
    color: var(--crm-text-muted, #667085);
}

.user-roles__list {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    padding: 0;
    margin: 0;
    list-style: none;
}

.user-roles__option {
    display: flex;
    align-items: flex-start;
    gap: 0.75rem;
    width: 100%;
    padding: 0.75rem 0.875rem;
    font-size: var(--crm-fs, 0.875rem);
    cursor: pointer;
    border: 1px solid var(--crm-border, #e4e7ec);
    border-radius: var(--crm-radius, 0.5rem);
    transition: background-color 0.15s ease, border-color 0.15s ease;
}

.user-roles__option:hover {
    background: var(--crm-hover, #f2f4f7);
}

.user-roles__option.is-on {
    background: var(--crm-primary-soft);
    border-color: var(--crm-primary);
}

.user-roles__option .form-check-input {
    flex: none;
    margin-top: 0.2rem;
}

.user-roles__name {
    display: block;
    font-weight: 500;
    color: var(--crm-text, #101828);
}

.user-roles__desc {
    display: block;
    font-size: var(--crm-fs-sm, 0.8125rem);
    color: var(--crm-text-muted, #667085);
}

.user-roles .badge {
    font-size: var(--crm-fs-xs, 0.75rem);
    font-weight: 500;
}

.user-roles__actions {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    margin-top: 1.75rem;
}

.user-roles__actions .btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: var(--crm-control-h, 2.25rem);
    font-size: var(--crm-fs, 0.875rem);
    font-weight: 500;
}

.user-roles__actions .btn-primary {
    min-height: 2.75rem;
}
</style>
