<script setup>
import { computed, ref } from 'vue';
import ConfirmModal from '@/components/ConfirmModal.vue';
import SlideOver from '@/components/SlideOver.vue';
import LeadForm from './LeadFormView.vue';
import LeadImport from './LeadImport.vue';

const props = defineProps({
    show: Boolean,
    /** null = create a new lead, otherwise edit this lead. */
    leadId: { type: [String, Number], default: null },
    owners: { type: Array, default: () => [] },
});
const emit = defineEmits(['close', 'saved', 'imported']);

const isEdit = computed(() => props.leadId !== null);
const formRef = ref(null);
const confirmDiscard = ref(false);

/** Every user-initiated close (X, Esc, backdrop) goes through here. */
function requestClose() {
    if (formRef.value?.isDirty) {
        confirmDiscard.value = true;
        return;
    }
    emit('close');
}

function discard() {
    confirmDiscard.value = false;
    emit('close');
}

function onSaved(lead, { another }) {
    emit('saved', lead);
    if (!another) emit('close');
}
</script>

<template>
    <SlideOver :show="show" :title="isEdit ? 'Edit Lead' : 'Create Lead'" width="46rem" @close="requestClose">
        <div class="drawer-scroll">
            <LeadForm
                ref="formRef"
                :key="leadId ?? 'new'"
                :lead-id="leadId"
                :owners="owners"
                @saved="onSaved"
                @cancel="requestClose"
            />

            <!-- Bulk import only makes sense when creating -->
            <LeadImport v-if="!isEdit" class="drawer-import" @imported="emit('imported', $event)" />
        </div>
    </SlideOver>

    <ConfirmModal
        :show="confirmDiscard"
        title="Discard changes?"
        message="You have unsaved changes. If you close now, they will be lost."
        @confirm="discard"
        @cancel="confirmDiscard = false"
    />
</template>

<style scoped>
.drawer-scroll {
    flex: 1 1 auto;
    min-height: 0;
    padding: 1.5rem;
    overflow-y: auto;
    scrollbar-width: thin;
}

.drawer-import {
    margin-top: 2rem;
}

.drawer-scroll {
    flex: 1 1 auto;
    min-height: 0;
    padding: 1.5rem;
    overflow-y: auto;
    overscroll-behavior: contain; /* new: scrolling to the end doesn't move the page */
    scrollbar-width: thin;
}
</style>