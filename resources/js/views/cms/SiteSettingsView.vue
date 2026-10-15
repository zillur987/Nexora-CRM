<script setup>
import { onMounted, reactive, ref } from 'vue';
import { siteSettingsApi } from '@/api/cms';
import { useToastStore } from '@/stores/toast';
import { usePermission } from '@/composables/usePermission';
import { parseApiError } from '@/utils/errors';
import LoadingBlock from '@/components/LoadingBlock.vue';
import ImageField from '@/components/cms/ImageField.vue';

const toast = useToastStore();
const { can } = usePermission();
const tab = ref('header');
const loading = ref(true);
const saving = ref(false);
const errors = ref({});

const header = reactive({ brand_name: '', search_label: '', search_url: '', cta_label: '', cta_url: '', phone: '', whatsapp: '', show_language_switch: false, languages: [] });
const hero = reactive({ headline: '', show_search: true });
const urls = reactive({ logo: null, video: null, poster: null });
const files = reactive({ logo_image: null, video: null, poster: null });
const removes = reactive({ logo_image: false, video: false, poster: false });

function applyHeader(d) {
    Object.assign(header, {
        brand_name: d.brand_name ?? '', search_label: d.search_label ?? '', search_url: d.search_url ?? '',
        cta_label: d.cta_label ?? '', cta_url: d.cta_url ?? '', phone: d.phone ?? '', whatsapp: d.whatsapp ?? '',
        show_language_switch: !!d.show_language_switch, languages: (d.languages ?? []).map((l) => ({ ...l })),
    });
    urls.logo = d.logo_image_url;
    files.logo_image = null;
    removes.logo_image = false;
}

function applyHero(d) {
    Object.assign(hero, { headline: d.headline ?? '', show_search: !!d.show_search });
    urls.video = d.video_url;
    urls.poster = d.poster_url;
    Object.assign(files, { video: null, poster: null });
    Object.assign(removes, { video: false, poster: false });
}

onMounted(async () => {
    try {
        const [h, b] = await Promise.all([siteSettingsApi.get('header'), siteSettingsApi.get('hero')]);
        applyHeader(h);
        applyHero(b);
    } catch (e) {
        toast.error(parseApiError(e).message);
    } finally {
        loading.value = false;
    }
});

const addLanguage = () => header.languages.length < 5 && header.languages.push({ code: '', label: '', short: '' });

async function save() {
    saving.value = true;
    errors.value = {};
    try {
        if (tab.value === 'header') {
            applyHeader(await siteSettingsApi.update('header', { ...header, logo_image: files.logo_image, remove_logo_image: removes.logo_image }));
        } else {
            applyHero(await siteSettingsApi.update('hero', { ...hero, video: files.video, remove_video: removes.video, poster: files.poster, remove_poster: removes.poster }));
        }
        toast.success('Settings saved.');
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
    <div class="card rounded-3 overflow-visible">
        <div class="card-header bg-white px-3 py-3 rounded-top-3">
            <h1 class="h5 mb-0">Header &amp; Banner Settings</h1>
        </div>

        <ul class="nav nav-pills gap-1 px-3 pt-3">
            <li class="nav-item"><button type="button" class="nav-link" :class="{ active: tab === 'header' }" @click="tab = 'header'">Header</button></li>
            <li class="nav-item"><button type="button" class="nav-link" :class="{ active: tab === 'hero' }" @click="tab = 'hero'">Banner</button></li>
        </ul>

        <LoadingBlock v-if="loading" />

        <form v-else class="card-body" style="max-width: 46rem" novalidate @submit.prevent="save">
            <template v-if="tab === 'header'">
                <div class="mb-3">
                    <label for="st-brand" class="form-label">Brand name</label>
                    <input id="st-brand" v-model.trim="header.brand_name" class="form-control" :class="{ 'is-invalid': errors.brand_name }" maxlength="60" />
                    <div v-if="errors.brand_name" class="invalid-feedback">{{ errors.brand_name }}</div>
                </div>
                <ImageField id="st-logo" v-model="files.logo_image" v-model:remove="removes.logo_image" label="Logo (optional)" :current-url="urls.logo" hint="Transparent PNG or WebP, up to 1 MB, about 480 × 120. Without a logo the brand name is shown as text." :error="errors.logo_image" />

                <div class="row g-3 mb-3">
                    <div class="col-sm-6"><label for="st-sl" class="form-label">Search link text</label><input id="st-sl" v-model.trim="header.search_label" class="form-control" :class="{ 'is-invalid': errors.search_label }" maxlength="30" /></div>
                    <div class="col-sm-6"><label for="st-su" class="form-label">Search link (URL)</label><input id="st-su" v-model.trim="header.search_url" class="form-control" :class="{ 'is-invalid': errors.search_url }" /></div>
                    <div class="col-sm-6"><label for="st-cl" class="form-label">Button text</label><input id="st-cl" v-model.trim="header.cta_label" class="form-control" :class="{ 'is-invalid': errors.cta_label }" maxlength="40" /></div>
                    <div class="col-sm-6"><label for="st-cu" class="form-label">Button link (URL)</label><input id="st-cu" v-model.trim="header.cta_url" class="form-control" :class="{ 'is-invalid': errors.cta_url }" /></div>
                    <div class="col-sm-6">
                        <label for="st-ph" class="form-label">Phone</label>
                        <input id="st-ph" v-model.trim="header.phone" class="form-control" :class="{ 'is-invalid': errors.phone }" placeholder="+8801XXXXXXXXX" />
                        <div v-if="errors.phone" class="invalid-feedback">{{ errors.phone }}</div>
                    </div>
                    <div class="col-sm-6">
                        <label for="st-wa" class="form-label">WhatsApp number</label>
                        <input id="st-wa" v-model.trim="header.whatsapp" class="form-control" :class="{ 'is-invalid': errors.whatsapp }" placeholder="8801XXXXXXXXX (digits only)" />
                        <div v-if="errors.whatsapp" class="invalid-feedback">{{ errors.whatsapp }}</div>
                    </div>
                </div>

                <div class="form-check form-switch mb-3">
                    <input id="st-lang" v-model="header.show_language_switch" class="form-check-input" type="checkbox" role="switch" />
                    <label for="st-lang" class="form-check-label">Show language switcher</label>
                </div>
                <div v-if="header.show_language_switch" class="mb-3">
                    <div v-for="(l, i) in header.languages" :key="i" class="row g-2 mb-2">
                        <div class="col-3"><input v-model.trim="l.code" class="form-control" placeholder="Code (en)" maxlength="5" :aria-label="`Language ${i + 1} code`" /></div>
                        <div class="col-5"><input v-model.trim="l.label" class="form-control" placeholder="Name (English)" maxlength="30" :aria-label="`Language ${i + 1} name`" /></div>
                        <div class="col-3"><input v-model.trim="l.short" class="form-control" placeholder="Short (EN)" maxlength="4" :aria-label="`Language ${i + 1} short name`" /></div>
                        <div class="col-1"><button type="button" class="btn btn-link text-danger px-0" aria-label="Remove language" @click="header.languages.splice(i, 1)"><i class="bi bi-x-lg"></i></button></div>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-secondary" :disabled="header.languages.length >= 5" @click="addLanguage"><i class="bi bi-plus-lg me-1"></i>Add language</button>
                </div>
            </template>

            <template v-else>
                <div class="mb-3">
                    <label for="st-hl" class="form-label">Banner headline <span class="text-muted">(for search engines and screen readers)</span></label>
                    <input id="st-hl" v-model.trim="hero.headline" class="form-control" :class="{ 'is-invalid': errors.headline }" maxlength="160" />
                    <div v-if="errors.headline" class="invalid-feedback">{{ errors.headline }}</div>
                </div>
                <ImageField id="st-video" v-model="files.video" v-model:remove="removes.video" kind="video" label="Background video" :current-url="urls.video" hint="MP4 (H.264) or WebM, up to 30 MB, 1920 × 1080, 10 to 20 seconds, no sound needed." :error="errors.video" />
                <ImageField id="st-poster" v-model="files.poster" v-model:remove="removes.poster" label="Poster image" :current-url="urls.poster" hint="Shown before the video plays and used alone if there is no video. 1920 × 1080, up to 5 MB." :error="errors.poster" />
                <div class="form-check form-switch mb-3">
                    <input id="st-search" v-model="hero.show_search" class="form-check-input" type="checkbox" role="switch" />
                    <label for="st-search" class="form-check-label">Show the property search bar on the banner</label>
                </div>
            </template>

            <button v-if="can('site_settings.edit')" type="submit" class="btn btn-primary" :disabled="saving">
                <span v-if="saving" class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Save
            </button>
        </form>
    </div>
</template>
