<script setup>
import { computed } from 'vue';
import { contactStatusMeta, dealStageMeta, leadStatusMeta } from '@/constants';

const props = defineProps({
    kind: { type: String, required: true }, // 'contact' | 'deal' | 'lead'
    value: { type: String, required: true },
});

const MAPS = { contact: contactStatusMeta, deal: dealStageMeta, lead: leadStatusMeta };

const meta = computed(() => {
    const map = MAPS[props.kind] ?? contactStatusMeta;
    return map[props.value] ?? { label: props.value, color: 'secondary' };
});
</script>

<template>
    <span class="badge" :class="`text-bg-${meta.color}`">{{ meta.label }}</span>
</template>