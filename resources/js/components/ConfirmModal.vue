<script setup>
defineProps({
    show: { type: Boolean, default: false },
    title: { type: String, default: 'Are you sure?' },
    message: { type: String, default: '' },
    confirmText: { type: String, default: 'Delete' },
    loading: { type: Boolean, default: false },
});
defineEmits(['confirm', 'cancel']);
</script>

<template>
    <Teleport to="body">
        <div v-if="show">
            <div class="modal d-block" tabindex="-1" @click.self="$emit('cancel')">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">{{ title }}</h5>
                            <button type="button" class="btn-close" @click="$emit('cancel')"></button>
                        </div>
                        <div class="modal-body">{{ message }}</div>
                        <div class="modal-footer">
                            <button class="btn btn-outline-secondary" :disabled="loading" @click="$emit('cancel')">Cancel</button>
                            <button class="btn btn-danger" :disabled="loading" @click="$emit('confirm')">
                                <span v-if="loading" class="spinner-border spinner-border-sm me-1"></span>{{ confirmText }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-backdrop show"></div>
        </div>
    </Teleport>
</template>
