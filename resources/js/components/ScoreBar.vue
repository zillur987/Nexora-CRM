<script setup>
import { computed } from 'vue';

const props = defineProps({ value: { type: Number, default: 0 } });

const color = computed(() => (props.value >= 70 ? 'success' : props.value >= 40 ? 'warning' : 'secondary'));
</script>

<template>
    <div class="d-flex align-items-center gap-2" style="min-width: 110px">
        <div class="progress flex-grow-1" style="height: 6px" role="progressbar" :aria-valuenow="value" aria-valuemin="0" aria-valuemax="100">
            <div class="progress-bar" :class="`bg-${color}`" :style="{ width: `${value}%` }"></div>
        </div>
        <small class="text-muted text-end" style="width: 2rem">{{ value }}</small>
    </div>
</template>
