<script setup>
import { onMounted, reactive, ref } from 'vue';
import { bannerSlidesApi } from '@/api/cms';
import { useToastStore } from '@/stores/toast';
import { parseApiError } from '@/utils/errors';
import ConfirmModal from '@/components/ConfirmModal.vue';
import LoadingBlock from '@/components/LoadingBlock.vue';
import RecordDrawer from '@/components/RecordDrawer.vue';
import BannerSlideForm from '@/views/cms/BannerSlideForm.vue';

const toast = useToastStore();
const slides = ref([]);
const loading = ref(true);
const error = ref('');

async function load() {
    try {
        slides.value = await bannerSlidesApi.list();
        error.value = '';
    } catch (e) {
        error.value = parseApiError(e).message;
    } finally {
        loading.value = false;
    }
}
onMounted(load);

const drawer = reactive({ show: false, slide: null });
const formRef = ref(null);
const confirmDiscard = ref(false);
const toDelete = ref(null);

const openCreate = () => Object.assign(drawer, { show: true, slide: null });
const openEdit = (slide) => Object.assign(drawer, { show: true, slide });
const closeDrawer = () => { confirmDiscard.value = false; drawer.show = false; };
const requestClose = () => (formRef.value?.isDirty ? (confirmDiscard.value = true) : closeDrawer());
const onSaved = async () => { await load(); closeDrawer(); };

async function run(action, okMessage) {
    try {
        await action();
        if (okMessage) toast.success(okMessage);
        await load();
    } catch (e) {
        toast.error(parseApiError(e).message);
    }
}

function move(index, dir) {
    const ids = slides.value.map((s) => s.id);
    const to = index + dir;
    if (to < 0 || to >= ids.length) return;
    [ids[index], ids[to]] = [ids[to], ids[index]];
    run(() => bannerSlidesApi.reorder(ids));
}

async function confirmDelete() {
    const slide = toDelete.value;
    toDelete.value = null;
    await run(() => bannerSlidesApi.remove(slide.id), 'Slide deleted.');
}

function status(s) {
    if (!s.is_active) return { text: 'Hidden', cls: 'text-bg-secondary' };
    return s.is_live ? { text: 'Live', cls: 'text-bg-success' } : { text: 'Scheduled', cls: 'text-bg-warning' };
}
</script>

<template>
    <div class="card rounded-3 overflow-visible">
        <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-2 px-3 py-3 rounded-top-3">
            <div>
                <h1 class="h5 mb-0">Banner Slider</h1>
                <p class="text-muted small mb-0">Slides of the slider under the banner. Order here is the order on the website (4 to 6 slides works best).</p>
            </div>
            <button v-can="'banners.create'" type="button" class="btn btn-primary" @click="openCreate"><i class="bi bi-plus-lg me-1"></i>Add slide</button>
        </div>

        <div v-if="error" class="alert alert-danger m-3">{{ error }}</div>
        <LoadingBlock v-else-if="loading" />

        <div v-else class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th scope="col">Image</th><th scope="col">Slide</th><th scope="col">Status</th><th scope="col" class="text-end"><span class="visually-hidden">Actions</span></th></tr></thead>
                <tbody>
                    <tr v-if="!slides.length"><td colspan="4" class="text-center text-muted py-5">No slides yet.</td></tr>
                    <tr v-for="(s, i) in slides" :key="s.id">
                        <td style="width: 7rem">
                            <img v-if="s.image_url" :src="s.image_url" alt="" class="rounded" style="width: 6rem; height: 3.4rem; object-fit: cover" />
                            <span v-else class="d-inline-flex align-items-center justify-content-center rounded bg-light text-muted" style="width: 6rem; height: 3.4rem"><i class="bi bi-image"></i></span>
                        </td>
                        <td>
                            <strong class="d-block">{{ s.name }}</strong>
                            <span class="small text-muted">{{ s.title_lines.join(' · ') }}</span>
                        </td>
                        <td><span class="badge" :class="status(s).cls">{{ status(s).text }}</span></td>
                        <td class="text-end text-nowrap">
                            <button v-can="'banners.edit'" type="button" class="btn btn-sm btn-link text-body" :disabled="i === 0" aria-label="Move up" @click="move(i, -1)"><i class="bi bi-arrow-up"></i></button>
                            <button v-can="'banners.edit'" type="button" class="btn btn-sm btn-link text-body" :disabled="i === slides.length - 1" aria-label="Move down" @click="move(i, 1)"><i class="bi bi-arrow-down"></i></button>
                            <button v-can="'banners.edit'" type="button" class="btn btn-sm btn-link text-body" :aria-label="s.is_active ? 'Hide slide' : 'Show slide'" @click="run(() => bannerSlidesApi.toggle(s.id))"><i class="bi" :class="s.is_active ? 'bi-eye' : 'bi-eye-slash'"></i></button>
                            <button v-can="'banners.edit'" type="button" class="btn btn-sm btn-link text-body" aria-label="Edit" @click="openEdit(s)"><i class="bi bi-pencil"></i></button>
                            <button v-can="'banners.delete'" type="button" class="btn btn-sm btn-link text-danger" aria-label="Delete" @click="toDelete = s"><i class="bi bi-trash"></i></button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <RecordDrawer :show="drawer.show" :title="drawer.slide ? 'Edit slide' : 'Add slide'" @close="requestClose">
        <BannerSlideForm v-if="drawer.show" ref="formRef" :key="drawer.slide?.id ?? 'new'" :slide="drawer.slide" @saved="onSaved" @cancel="closeDrawer" />
    </RecordDrawer>

    <ConfirmModal :show="confirmDiscard" title="Discard changes?" message="You have unsaved changes. If you close now, they will be lost." confirm-text="Discard" @confirm="closeDrawer" @cancel="confirmDiscard = false" />
    <ConfirmModal :show="!!toDelete" title="Delete slide?" :message="`“${toDelete?.name}” will be deleted.`" confirm-text="Delete" @confirm="confirmDelete" @cancel="toDelete = null" />
</template>
