<script setup>
import { computed, reactive, ref } from 'vue';
import { menusApi } from '@/api/cms';
import { useToastStore } from '@/stores/toast';
import { parseApiError } from '@/utils/errors';
import ImageField from '@/components/cms/ImageField.vue';

const props = defineProps({
    /** Existing item when editing; null when creating. */
    item: { type: Object, default: null },
    /** Create mode: link | mega | column | item, and the parent it is added to. */
    type: { type: String, default: 'link' },
    parentId: { type: Number, default: null },
});
const emit = defineEmits(['saved', 'cancel']);

const toast = useToastStore();
const editing = computed(() => !!props.item);
const kind = computed(() => props.item?.type ?? props.type);

const form = reactive({
    label: props.item?.label ?? '',
    description: props.item?.description ?? '',
    url: props.item?.url ?? (kind.value === 'link' || kind.value === 'item' ? '' : '#'),
    target: props.item?.target ?? '_self',
    is_active: props.item?.is_active ?? true,
    meta_text: props.item?.meta_text ?? '',
    feature_label: props.item?.feature_label ?? '',
    feature_title: props.item?.feature_title ?? '',
    feature_sub: props.item?.feature_sub ?? '',
    feature_url: props.item?.feature_url ?? '',
});
const featureImage = ref(null);
const removeImage = ref(false);
const errors = ref({});
const saving = ref(false);

const snapshot = JSON.stringify(form);
const isDirty = computed(() => JSON.stringify(form) !== snapshot || !!featureImage.value || removeImage.value);
defineExpose({ isDirty });

const TITLES = { link: 'link', mega: 'mega menu', column: 'column', item: 'link' };
const hasUrl = computed(() => kind.value !== 'column');

function payload() {
    const base = { label: form.label, is_active: form.is_active };
    if (hasUrl.value) Object.assign(base, { url: form.url, target: form.target });
    if (kind.value === 'item') base.description = form.description;
    if (kind.value === 'mega') {
        Object.assign(base, {
            meta_text: form.meta_text, feature_label: form.feature_label, feature_title: form.feature_title,
            feature_sub: form.feature_sub, feature_url: form.feature_url,
            feature_image: featureImage.value, remove_feature_image: removeImage.value,
        });
    }
    if (!editing.value) Object.assign(base, { type: kind.value, parent_id: props.parentId });
    return base;
}

async function submit() {
    saving.value = true;
    errors.value = {};
    try {
        const saved = editing.value ? await menusApi.update(props.item.id, payload()) : await menusApi.create(payload());
        toast.success('Menu saved.');
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
    <form novalidate @submit.prevent="submit">
        <p class="text-muted small mb-3">{{ editing ? 'Editing' : 'Adding' }} a {{ TITLES[kind] }}.</p>

        <div class="mb-3">
            <label for="mi-label" class="form-label">{{ kind === 'column' ? 'Column heading' : 'Label' }}</label>
            <input id="mi-label" v-model.trim="form.label" class="form-control" :class="{ 'is-invalid': errors.label }" maxlength="120" required />
            <div v-if="errors.label" class="invalid-feedback">{{ errors.label }}</div>
        </div>

        <div v-if="kind === 'item'" class="mb-3">
            <label for="mi-desc" class="form-label">Short description</label>
            <input id="mi-desc" v-model.trim="form.description" class="form-control" :class="{ 'is-invalid': errors.description }" maxlength="160" />
            <div v-if="errors.description" class="invalid-feedback">{{ errors.description }}</div>
        </div>

        <template v-if="hasUrl">
            <div class="mb-3">
                <label for="mi-url" class="form-label">Link (URL)</label>
                <input id="mi-url" v-model.trim="form.url" class="form-control" :class="{ 'is-invalid': errors.url }" placeholder="/about, #projects or https://…" />
                <div v-if="errors.url" class="invalid-feedback">{{ errors.url }}</div>
            </div>
            <div class="mb-3">
                <label for="mi-target" class="form-label">Open in</label>
                <select id="mi-target" v-model="form.target" class="form-select">
                    <option value="_self">Same tab</option>
                    <option value="_blank">New tab</option>
                </select>
            </div>
        </template>

        <template v-if="kind === 'mega'">
            <div class="mb-3">
                <label for="mi-meta" class="form-label">Panel subtitle</label>
                <input id="mi-meta" v-model.trim="form.meta_text" class="form-control" maxlength="160" placeholder="Shown on the right of the panel title" />
            </div>

            <h3 class="h6 mt-4">Featured card (right side of the panel)</h3>
            <ImageField id="mi-image" v-model="featureImage" v-model:remove="removeImage" label="Image" :current-url="item?.feature_image_url" hint="JPG, PNG or WebP, up to 4 MB. Best at 16:10, e.g. 1200 × 750." :error="errors.feature_image" />
            <div class="mb-3"><label for="mi-fl" class="form-label">Small label</label><input id="mi-fl" v-model.trim="form.feature_label" class="form-control" maxlength="80" /></div>
            <div class="mb-3"><label for="mi-ft" class="form-label">Title</label><input id="mi-ft" v-model.trim="form.feature_title" class="form-control" maxlength="120" /></div>
            <div class="mb-3"><label for="mi-fs" class="form-label">Subtitle</label><input id="mi-fs" v-model.trim="form.feature_sub" class="form-control" maxlength="160" /></div>
            <div class="mb-3">
                <label for="mi-fu" class="form-label">Card link (URL)</label>
                <input id="mi-fu" v-model.trim="form.feature_url" class="form-control" :class="{ 'is-invalid': errors.feature_url }" />
                <div v-if="errors.feature_url" class="invalid-feedback">{{ errors.feature_url }}</div>
            </div>
        </template>

        <div class="form-check form-switch mb-4">
            <input id="mi-active" v-model="form.is_active" class="form-check-input" type="checkbox" role="switch" />
            <label for="mi-active" class="form-check-label">Visible on the website</label>
        </div>
        <div v-if="errors.parent_id" class="alert alert-danger py-2 small">{{ errors.parent_id }}</div>

        <div class="d-flex flex-column gap-1">
            <button type="submit" class="btn btn-primary" :disabled="saving">
                <span v-if="saving" class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Save
            </button>
            <button type="button" class="btn btn-link text-decoration-none" :disabled="saving" @click="emit('cancel')">Cancel</button>
        </div>
    </form>
</template>
