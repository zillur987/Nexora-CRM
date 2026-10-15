<script setup>
import { onMounted, reactive, ref } from 'vue';
import { menusApi } from '@/api/cms';
import { useToastStore } from '@/stores/toast';
import { parseApiError } from '@/utils/errors';
import ConfirmModal from '@/components/ConfirmModal.vue';
import LoadingBlock from '@/components/LoadingBlock.vue';
import RecordDrawer from '@/components/RecordDrawer.vue';
import MenuTreeNode from '@/components/cms/MenuTreeNode.vue';
import MenuItemForm from '@/views/cms/MenuItemForm.vue';

const toast = useToastStore();
const tree = ref([]);
const loading = ref(true);
const error = ref('');

async function load() {
    try {
        tree.value = await menusApi.tree();
        error.value = '';
    } catch (e) {
        error.value = parseApiError(e).message;
    } finally {
        loading.value = false;
    }
}
onMounted(load);

/* Drawer */
const drawer = reactive({ show: false, item: null, type: 'link', parentId: null });
const formRef = ref(null);
const confirmDiscard = ref(false);
const toDelete = ref(null);

const openCreate = (type, parentId = null) => Object.assign(drawer, { show: true, item: null, type, parentId });
const openEdit = (item) => Object.assign(drawer, { show: true, item, type: item.type, parentId: item.parent_id });
const closeDrawer = () => { confirmDiscard.value = false; drawer.show = false; };
const requestClose = () => (formRef.value?.isDirty ? (confirmDiscard.value = true) : closeDrawer());
const onSaved = async () => { await load(); closeDrawer(); };

/* Row actions */
async function run(action, okMessage) {
    try {
        await action();
        if (okMessage) toast.success(okMessage);
        await load();
    } catch (e) {
        toast.error(parseApiError(e).message);
    }
}

const onToggle = (node) => run(() => menusApi.toggle(node.id));
const onAdd = (node, type) => openCreate(type, node.id);

function onMove(node, siblings, dir) {
    const ids = siblings.map((s) => s.id);
    const from = ids.indexOf(node.id);
    const to = from + dir;
    if (to < 0 || to >= ids.length) return;
    [ids[from], ids[to]] = [ids[to], ids[from]];
    run(() => menusApi.reorder(node.parent_id, ids));
}

async function confirmDelete() {
    const node = toDelete.value;
    toDelete.value = null;
    await run(() => menusApi.remove(node.id), 'Menu item deleted.');
}
</script>

<template>
    <div class="card rounded-3 overflow-visible">
        <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-2 px-3 py-3 rounded-top-3">
            <div>
                <h1 class="h5 mb-0">Header Menu</h1>
                <p class="text-muted small mb-0">Top links, mega menus (columns and links) and the featured card. Changes appear on the website within a minute.</p>
            </div>
            <div class="d-flex gap-2">
                <button v-can="'menus.create'" type="button" class="btn btn-outline-secondary bg-white text-body" @click="openCreate('link')"><i class="bi bi-link-45deg me-1"></i>Add link</button>
                <button v-can="'menus.create'" type="button" class="btn btn-primary" @click="openCreate('mega')"><i class="bi bi-grid-3x3-gap me-1"></i>Add mega menu</button>
            </div>
        </div>

        <div class="card-body bg-light">
            <div v-if="error" class="alert alert-danger">{{ error }}</div>
            <LoadingBlock v-else-if="loading" />
            <p v-else-if="!tree.length" class="text-center text-muted py-5 mb-0">No menu items yet.</p>
            <ul v-else class="p-0 m-0">
                <MenuTreeNode v-for="node in tree" :key="node.id" :node="node" :siblings="tree" @edit="openEdit" @add="onAdd" @toggle="onToggle" @remove="(n) => (toDelete = n)" @move="onMove" />
            </ul>
        </div>
    </div>

    <RecordDrawer :show="drawer.show" :title="drawer.item ? 'Edit menu item' : 'Add menu item'" @close="requestClose">
        <MenuItemForm v-if="drawer.show" ref="formRef" :key="`${drawer.item?.id ?? 'new'}-${drawer.type}-${drawer.parentId}`" :item="drawer.item" :type="drawer.type" :parent-id="drawer.parentId" @saved="onSaved" @cancel="closeDrawer" />
    </RecordDrawer>

    <ConfirmModal :show="confirmDiscard" title="Discard changes?" message="You have unsaved changes. If you close now, they will be lost." confirm-text="Discard" @confirm="closeDrawer" @cancel="confirmDiscard = false" />
    <ConfirmModal :show="!!toDelete" title="Delete menu item?" :message="toDelete?.children?.length ? `“${toDelete.label}” and everything inside it will be deleted.` : `“${toDelete?.label}” will be deleted.`" confirm-text="Delete" @confirm="confirmDelete" @cancel="toDelete = null" />
</template>
