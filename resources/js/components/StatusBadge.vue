<script setup>
import { computed } from 'vue';
import { contactStatusMeta, dealStageMeta } from '@/constants';

const props = defineProps({
    kind: { type: String, required: true }, // 'contact' | 'deal'
    value: { type: String, required: true },
});

const meta = computed(() => {
    const map = props.kind === 'deal' ? dealStageMeta : contactStatusMeta;
    return map[props.value] ?? { label: props.value, color: 'secondary' };
});
</script>

<template>
    <span class="badge" :class="`text-bg-${meta.color}`">{{ meta.label }}</span>
</template>
