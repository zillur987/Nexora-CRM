<script setup>
import { computed, nextTick, ref } from 'vue';
import { parseApiError } from '@/utils/errors';

/**
 * A <select> with a "+" button that adds a new option on the spot
 * (used for Industry and Company Type).
 */
const props = defineProps({
    modelValue: { type: [Number, String], default: '' },
    options: { type: Array, default: () => [] }, // [{ id, name }]
    placeholder: { type: String, default: 'Select…' },
    addLabel: { type: String, default: 'Add new' },
    addPlaceholder: { type: String, default: 'New value' },
    invalid: { type: Boolean, default: false },
    /** async (name) => ({ id, name }): persists the new option. */
    create: { type: Function, required: true },
});
const emit = defineEmits(['update:modelValue', 'created']);

const model = computed({
    get: () => props.modelValue,
    set: (value) => emit('update:modelValue', value),
});

const adding = ref(false);
const name = ref('');
const saving = ref(false);
const error = ref('');
const inputEl = ref(null);

function toggle() {
    adding.value = !adding.value;
    error.value = '';
    if (adding.value) nextTick(() => inputEl.value?.focus());
}

async function add() {
    const value = name.value.trim();
    if (!value || saving.value) return;

    saving.value = true;
    error.value = '';

    try {
        const created = await props.create(value);
        emit('created', created); // the parent adds it to `options` first...
        emit('update:modelValue', created.id); // ...then it gets selected
        name.value = '';
        adding.value = false;
    } catch (e) {
        const parsed = parseApiError(e);
        error.value = parsed.fields.name ?? parsed.message;
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <div>
        <div class="input-group">
            <select v-model="model" class="form-select" :class="{ 'is-invalid': invalid }">
                <option value="">{{ placeholder }}</option>
                <option v-for="o in options" :key="o.id" :value="o.id">{{ o.name }}</option>
            </select>
            <button
                type="button"
                class="btn btn-outline-secondary"
                :title="addLabel"
                :aria-label="addLabel"
                :aria-expanded="adding"
                @click="toggle"
            >
                <i class="bi" :class="adding ? 'bi-x-lg' : 'bi-plus-lg'"></i>
            </button>
        </div>

        <div v-if="adding" class="input-group mt-2">
            <input
                ref="inputEl"
                v-model="name"
                type="text"
                maxlength="120"
                class="form-control"
                :class="{ 'is-invalid': error }"
                :placeholder="addPlaceholder"
                @keydown.enter.prevent="add"
                @keydown.esc.stop="toggle"
            />
            <button type="button" class="btn btn-primary" :disabled="saving || !name.trim()" @click="add">
                <span v-if="saving" class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Add
            </button>
        </div>

        <div v-if="error" class="invalid-feedback d-block">{{ error }}</div>
    </div>
</template>
