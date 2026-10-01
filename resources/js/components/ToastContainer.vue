<script setup>
import { useToastStore } from '@/stores/toast';

const toast = useToastStore();
</script>

<template>
    <div class="toast-stack">
        <TransitionGroup name="fade">
            <div v-for="t in toast.items" :key="t.id" class="alert shadow-sm d-flex justify-content-between align-items-start" :class="`alert-${t.type}`">
                <span>{{ t.message }}</span>
                <button type="button" class="btn-close ms-2" @click="toast.dismiss(t.id)"></button>
            </div>
        </TransitionGroup>
    </div>
</template>
