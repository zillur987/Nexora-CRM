<script setup>
import { computed, reactive, ref } from 'vue';
import { bannerSlidesApi } from '@/api/cms';
import { useToastStore } from '@/stores/toast';
import { parseApiError } from '@/utils/errors';
import ImageField from '@/components/cms/ImageField.vue';

const props = defineProps({ slide: { type: Object, default: null } });
const emit = defineEmits(['saved', 'cancel']);

const toast = useToastStore();
const editing = computed(() => !!props.slide);

const pad = (n) => String(n).padStart(2, '0');
const toLocalInput = (iso) => {
    if (!iso) return '';
    const d = new Date(iso);
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
};

const form = reactive({
    name: props.slide?.name ?? '',
    location_label: props.slide?.location_label ?? '',
    title_lines: [...(props.slide?.title_lines ?? ['', ''])],
    text: props.slide?.text ?? '',
    facts: Array.from({ length: 4 }, (_, i) => ({ ...(props.slide?.facts?.[i] ?? { value: '', label: '' }) })),
    cta_label: props.slide?.cta_label ?? '',
    cta_url: props.slide?.cta_url ?? '',
    gradient: props.slide?.gradient ?? 'a',
    is_active: props.slide?.is_active ?? true,
    starts_at: toLocalInput(props.slide?.starts_at),
    ends_at: toLocalInput(props.slide?.ends_at),
});
while (form.title_lines.length < 3) form.title_lines.push('');

const image = ref(null);
const removeImage = ref(false);
const errors = ref({});
const saving = ref(false);

const snapshot = JSON.stringify(form);
const isDirty = computed(() => JSON.stringify(form) !== snapshot || !!image.value || removeImage.value);
defineExpose({ isDirty });

const err = (key) => errors.value[key];

async function submit() {
    saving.value = true;
    errors.value = {};

    const payload = {
        ...form,
        title_lines: form.title_lines.map((l) => l.trim()).filter(Boolean),
        facts: form.facts.filter((f) => f.value.trim() || f.label.trim()),
        starts_at: form.starts_at || null,
        ends_at: form.ends_at || null,
        image: image.value,
        remove_image: removeImage.value,
    };

    try {
        const saved = editing.value ? await bannerSlidesApi.update(props.slide.id, payload) : await bannerSlidesApi.create(payload);
        toast.success('Slide saved.');
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
        <ImageField id="bs-image" v-model="image" v-model:remove="removeImage" label="Slide image" :current-url="slide?.image_url" hint="JPG, PNG or WebP, up to 5 MB. Use 1920 × 1080 (16:9), at least 1280 × 640. Keep the subject away from the left third, where the text sits." :error="err('image')" />

        <div class="row g-3 mb-3">
            <div class="col-sm-6">
                <label for="bs-name" class="form-label">Tab name</label>
                <input id="bs-name" v-model.trim="form.name" class="form-control" :class="{ 'is-invalid': err('name') }" maxlength="80" />
                <div v-if="err('name')" class="invalid-feedback">{{ err('name') }}</div>
            </div>
            <div class="col-sm-6">
                <label for="bs-loc" class="form-label">Small location label</label>
                <input id="bs-loc" v-model.trim="form.location_label" class="form-control" maxlength="120" />
            </div>
        </div>

        <fieldset class="mb-3">
            <legend class="form-label fs-6">Headline (up to 3 short lines)</legend>
            <input v-for="(_, i) in form.title_lines" :key="i" v-model.trim="form.title_lines[i]" class="form-control mb-2" :class="{ 'is-invalid': err('title_lines') }" maxlength="60" :aria-label="`Headline line ${i + 1}`" />
            <div v-if="err('title_lines')" class="invalid-feedback d-block">{{ err('title_lines') }}</div>
        </fieldset>

        <div class="mb-3">
            <label for="bs-text" class="form-label">Description</label>
            <textarea id="bs-text" v-model.trim="form.text" class="form-control" rows="3" maxlength="400"></textarea>
        </div>

        <fieldset class="mb-3">
            <legend class="form-label fs-6">Key facts (up to 4)</legend>
            <div v-for="(f, i) in form.facts" :key="i" class="row g-2 mb-2">
                <div class="col-6"><input v-model.trim="f.value" class="form-control" :class="{ 'is-invalid': err(`facts.${i}.value`) }" maxlength="40" placeholder="Value, e.g. 5 Katha" :aria-label="`Fact ${i + 1} value`" /></div>
                <div class="col-6"><input v-model.trim="f.label" class="form-control" :class="{ 'is-invalid': err(`facts.${i}.label`) }" maxlength="40" placeholder="Label, e.g. Plot size" :aria-label="`Fact ${i + 1} label`" /></div>
            </div>
        </fieldset>

        <div class="row g-3 mb-3">
            <div class="col-sm-5">
                <label for="bs-cta" class="form-label">Button text</label>
                <input id="bs-cta" v-model.trim="form.cta_label" class="form-control" :class="{ 'is-invalid': err('cta_label') }" maxlength="80" />
                <div v-if="err('cta_label')" class="invalid-feedback">{{ err('cta_label') }}</div>
            </div>
            <div class="col-sm-7">
                <label for="bs-url" class="form-label">Button link (URL)</label>
                <input id="bs-url" v-model.trim="form.cta_url" class="form-control" :class="{ 'is-invalid': err('cta_url') }" placeholder="/projects/… or #projects" />
                <div v-if="err('cta_url')" class="invalid-feedback">{{ err('cta_url') }}</div>
            </div>
        </div>

        <div class="mb-3">
            <label for="bs-grad" class="form-label">Colour shown while the image loads</label>
            <select id="bs-grad" v-model="form.gradient" class="form-select">
                <option v-for="g in ['a', 'b', 'c', 'd', 'e', 'f']" :key="g" :value="g">Style {{ g.toUpperCase() }}</option>
            </select>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-sm-6">
                <label for="bs-from" class="form-label">Show from <span class="text-muted">(optional)</span></label>
                <input id="bs-from" v-model="form.starts_at" type="datetime-local" class="form-control" :class="{ 'is-invalid': err('starts_at') }" />
            </div>
            <div class="col-sm-6">
                <label for="bs-to" class="form-label">Show until <span class="text-muted">(optional)</span></label>
                <input id="bs-to" v-model="form.ends_at" type="datetime-local" class="form-control" :class="{ 'is-invalid': err('ends_at') }" />
                <div v-if="err('ends_at')" class="invalid-feedback">{{ err('ends_at') }}</div>
            </div>
        </div>

        <div class="form-check form-switch mb-4">
            <input id="bs-active" v-model="form.is_active" class="form-check-input" type="checkbox" role="switch" />
            <label for="bs-active" class="form-check-label">Visible on the website</label>
        </div>

        <div class="d-flex flex-column gap-1">
            <button type="submit" class="btn btn-primary" :disabled="saving">
                <span v-if="saving" class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Save slide
            </button>
            <button type="button" class="btn btn-link text-decoration-none" :disabled="saving" @click="emit('cancel')">Cancel</button>
        </div>
    </form>
</template>
