<script setup>
import { computed } from 'vue';

/** One row of the menu tree. Renders its own children recursively. */
const props = defineProps({
    node: { type: Object, required: true },
    siblings: { type: Array, required: true },
});
const emit = defineEmits(['edit', 'add', 'toggle', 'remove', 'move']);

const TYPES = {
    link: { label: 'Link', badge: 'text-bg-secondary', add: null },
    mega: { label: 'Mega menu', badge: 'text-bg-primary', add: { type: 'column', text: 'Add column' } },
    column: { label: 'Column', badge: 'text-bg-info', add: { type: 'item', text: 'Add link' } },
    item: { label: 'Link', badge: 'text-bg-light border', add: null },
};
const meta = computed(() => TYPES[props.node.type]);
const index = computed(() => props.siblings.findIndex((s) => s.id === props.node.id));
</script>

<template>
    <li class="menu-node">
        <div class="menu-node__row" :class="{ 'is-off': !node.is_active }">
            <span class="badge" :class="meta.badge">{{ meta.label }}</span>
            <span class="menu-node__label text-truncate">{{ node.label }}</span>
            <span v-if="node.url" class="menu-node__url text-truncate">{{ node.url }}</span>

            <span class="menu-node__actions no-print">
                <button v-if="meta.add" v-can="'menus.create'" type="button" class="btn btn-sm btn-outline-secondary" @click="emit('add', node, meta.add.type)">
                    <i class="bi bi-plus-lg me-1"></i>{{ meta.add.text }}
                </button>
                <button v-can="'menus.edit'" type="button" class="btn btn-sm btn-link text-body" :disabled="index === 0" aria-label="Move up" @click="emit('move', node, siblings, -1)"><i class="bi bi-arrow-up"></i></button>
                <button v-can="'menus.edit'" type="button" class="btn btn-sm btn-link text-body" :disabled="index === siblings.length - 1" aria-label="Move down" @click="emit('move', node, siblings, 1)"><i class="bi bi-arrow-down"></i></button>
                <div v-can="'menus.edit'" class="form-check form-switch m-0">
                    <input class="form-check-input" type="checkbox" role="switch" :checked="node.is_active" :aria-label="`Show ${node.label}`" @change="emit('toggle', node)" />
                </div>
                <button v-can="'menus.edit'" type="button" class="btn btn-sm btn-link text-body" aria-label="Edit" @click="emit('edit', node)"><i class="bi bi-pencil"></i></button>
                <button v-can="'menus.delete'" type="button" class="btn btn-sm btn-link text-danger" aria-label="Delete" @click="emit('remove', node)"><i class="bi bi-trash"></i></button>
            </span>
        </div>

        <ul v-if="node.children?.length" class="menu-node__children">
            <MenuTreeNode
                v-for="child in node.children"
                :key="child.id"
                :node="child"
                :siblings="node.children"
                @edit="(...a) => emit('edit', ...a)"
                @add="(...a) => emit('add', ...a)"
                @toggle="(...a) => emit('toggle', ...a)"
                @remove="(...a) => emit('remove', ...a)"
                @move="(...a) => emit('move', ...a)"
            />
        </ul>
    </li>
</template>

<style scoped>
.menu-node { list-style: none; }
.menu-node__row { display: flex; align-items: center; gap: 0.75rem; padding: 0.5rem 0.75rem; margin-bottom: 0.375rem; background: #fff; border: 1px solid var(--crm-border, #e4e7ec); border-radius: var(--crm-radius, 0.5rem); }
.menu-node__row.is-off { opacity: 0.55; }
.menu-node__label { font-weight: 500; min-width: 0; }
.menu-node__url { flex: 1; min-width: 0; font-size: var(--crm-fs-sm, 0.8125rem); color: var(--crm-text-muted, #667085); }
.menu-node__actions { display: inline-flex; align-items: center; gap: 0.25rem; margin-left: auto; flex: none; }
.menu-node__children { padding: 0 0 0 1.5rem; margin: 0; }
</style>
