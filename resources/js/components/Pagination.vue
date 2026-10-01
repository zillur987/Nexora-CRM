<script setup>
import { computed } from 'vue';

const props = defineProps({ meta: { type: Object, required: true } });
const emit = defineEmits(['change']);

const current = computed(() => props.meta.current_page);
const last = computed(() => props.meta.last_page);

const pages = computed(() => {
    const from = Math.max(1, current.value - 2);
    const to = Math.min(last.value, current.value + 2);
    return Array.from({ length: to - from + 1 }, (_, i) => from + i);
});

const go = (p) => {
    if (p >= 1 && p <= last.value && p !== current.value) emit('change', p);
};
</script>

<template>
    <div class="d-flex justify-content-between align-items-center">
        <small class="text-muted">
            Showing {{ meta.from ?? 0 }}–{{ meta.to ?? 0 }} of {{ meta.total }}
        </small>
        <ul v-if="last > 1" class="pagination pagination-sm mb-0">
            <li class="page-item" :class="{ disabled: current === 1 }">
                <button class="page-link" @click="go(current - 1)">&laquo;</button>
            </li>
            <li v-for="p in pages" :key="p" class="page-item" :class="{ active: p === current }">
                <button class="page-link" @click="go(p)">{{ p }}</button>
            </li>
            <li class="page-item" :class="{ disabled: current === last }">
                <button class="page-link" @click="go(current + 1)">&raquo;</button>
            </li>
        </ul>
    </div>
</template>
