<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';

/** File picker with preview for images (and a file name for video). v-model = new File | null, v-model:remove = delete current. */
const props = defineProps({
    id: { type: String, required: true },
    label: { type: String, required: true },
    modelValue: { type: [File, null], default: null },
    remove: { type: Boolean, default: false },
    currentUrl: { type: String, default: null },
    kind: { type: String, default: 'image' },
    hint: { type: String, default: '' },
    error: { type: String, default: '' },
});
const emit = defineEmits(['update:modelValue', 'update:remove']);

const input = ref(null);
const preview = ref(null);
const isVideo = computed(() => props.kind === 'video');
const accept = computed(() => (isVideo.value ? 'video/mp4,video/webm' : 'image/jpeg,image/png,image/webp'));
const shown = computed(() => (props.remove ? null : preview.value || props.currentUrl));

watch(() => props.modelValue, (file) => {
    if (preview.value) URL.revokeObjectURL(preview.value);
    preview.value = file && !isVideo.value ? URL.createObjectURL(file) : null;
    if (!file && input.value) input.value.value = '';
});
onBeforeUnmount(() => preview.value && URL.revokeObjectURL(preview.value));

function onPick(e) {
    emit('update:modelValue', e.target.files?.[0] ?? null);
    emit('update:remove', false);
}
</script>

<template>
    <div class="mb-3">
        <label :for="id" class="form-label">{{ label }}</label>

        <div v-if="shown && !isVideo" class="cms-image mb-2"><img :src="shown" alt="" /></div>
        <video v-else-if="shown && isVideo && !modelValue" :src="shown" class="cms-image mb-2" muted controls preload="metadata" />
        <p v-if="modelValue && isVideo" class="small text-muted mb-2"><i class="bi bi-film me-1"></i>{{ modelValue.name }}</p>

        <input :id="id" ref="input" type="file" class="form-control" :class="{ 'is-invalid': error }" :accept="accept" @change="onPick" />
        <div v-if="error" class="invalid-feedback">{{ error }}</div>
        <div v-if="hint" class="form-text">{{ hint }}</div>

        <div v-if="currentUrl && !modelValue" class="form-check mt-2">
            <input :id="`${id}-remove`" class="form-check-input" type="checkbox" :checked="remove" @change="emit('update:remove', $event.target.checked)" />
            <label :for="`${id}-remove`" class="form-check-label small">Remove current file</label>
        </div>
    </div>
</template>

<style scoped>
.cms-image { display: block; max-width: 100%; max-height: 11rem; border: 1px solid var(--crm-border, #e4e7ec); border-radius: var(--crm-radius, 0.5rem); overflow: hidden; background: #f2f4f7; }
.cms-image img { display: block; max-width: 100%; max-height: 11rem; object-fit: cover; }
</style>
