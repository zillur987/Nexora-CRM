<script setup>
defineProps({
    label: { type: String, required: true },
    error: { type: String, default: '' },
    required: { type: Boolean, default: false },
    hint: { type: String, default: '' },
});
</script>

<template>
    <div>
        <label class="form-label">
            {{ label }} <span v-if="required" class="text-danger">*</span>
        </label>
        <slot />
        <div v-if="error" class="invalid-feedback d-block">{{ error }}</div>
        <div v-else-if="hint" class="form-text">{{ hint }}</div>
    </div>
</template>
