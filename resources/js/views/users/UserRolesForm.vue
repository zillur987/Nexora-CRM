<script setup>
import { computed, onMounted, ref } from 'vue';
import { userAccessApi } from '@/api/access';
import { useToastStore } from '@/stores/toast';
import { parseApiError } from '@/utils/errors';
import LoadingBlock from '@/components/LoadingBlock.vue';

const props = defineProps({
    userId: { type: [String, Number], required: true },
    /** [{ id, name, slug, description, is_system }] from GET /access/users/lookups */
    roles: { type: Array, default: () => [] },
});
const emit = defineEmits(['saved', 'cancel']);

const toast = useToastStore();

const user = ref(null);
const selected = ref([]);
const errors = ref({});
const loading = ref(true);
const saving = ref(false);

const serialize = () => JSON.stringify([...selected.value].sort((a, b) => a - b));
const snapshot = ref(serialize());
const isDirty = computed(() => serialize() !== snapshot.value);
defineExpose({ isDirty });

onMounted(async () => {
    try {
        user.value = await userAccessApi.get(props.userId);
        selected.value = [...(user.value.role_ids ?? [])];
        snapshot.value = serialize();
    } catch {
        toast.error('Could not load the user.');
        emit('cancel');
    } finally {
        loading.value = false;
    }
});

async function submit() {
    saving.value = true;
    errors.value = {};

    try {
        const saved = await userAccessApi.updateRoles(props.userId, selected.value);
        snapshot.value = serialize();
        toast.success('Roles updated.');
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
        <div class="user-roles__user">
            <span class="user-roles__avatar" aria-hidden="true"><i class="bi bi-person"></i></span>
            <div class="min-w-0">
                <strong class="d-block text-truncate">{{ user.name }}</strong>
                <span class="user-roles__email">{{ user.email }}</span>
            </div>
        </div>

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
            <div v-if="!selected.length" class="alert alert-warning py-2 small mt-3 mb-0" role="note">
                <i class="bi bi-exclamation-triangle me-2"></i>With no role, this user can't open any protected page.
            </div>
        </section>

        <div class="user-roles__actions">
            <button type="submit" class="btn btn-primary" :disabled="saving">
                <span v-if="saving" class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Save Roles
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
