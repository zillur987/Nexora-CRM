<script setup>
import { reactive, watch } from 'vue';
import FormField from '@/components/FormField.vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    lead: { type: Object, default: null },
    loading: { type: Boolean, default: false },
    errors: { type: Object, default: () => ({}) },
});
const emit = defineEmits(['confirm', 'cancel']);

const form = reactive({ create_deal: true, deal_title: '', deal_amount: '', expected_close_date: '' });

// Pre-fill every time the modal opens for a lead.
watch(
    () => props.show,
    (open) => {
        if (!open || !props.lead) return;
        Object.assign(form, {
            create_deal: true,
            deal_title: `${props.lead.company || props.lead.full_name} – Opportunity`,
            deal_amount: props.lead.estimated_value ?? '',
            expected_close_date: '',
        });
    },
);

function submit() {
    emit('confirm', {
        create_deal: form.create_deal,
        ...(form.create_deal && {
            deal_title: form.deal_title,
            deal_amount: form.deal_amount === '' ? null : form.deal_amount,
            expected_close_date: form.expected_close_date || null,
        }),
    });
}
</script>

<template>
    <Teleport to="body">
        <div v-if="show && lead">
            <div class="modal d-block" tabindex="-1" @click.self="$emit('cancel')">
                <div class="modal-dialog modal-dialog-centered">
                    <form class="modal-content" @submit.prevent="submit">
                        <div class="modal-header">
                            <h5 class="modal-title">Convert {{ lead.full_name }}</h5>
                            <button type="button" class="btn-close" @click="$emit('cancel')"></button>
                        </div>
                        <div class="modal-body">
                            <p class="text-muted small">
                                A contact is created from this lead (or an existing contact with the same email is reused).
                            </p>

                            <div class="form-check form-switch mb-3">
                                <input id="create_deal" v-model="form.create_deal" class="form-check-input" type="checkbox" />
                                <label class="form-check-label" for="create_deal">Also create a deal</label>
                            </div>

                            <div v-if="form.create_deal" class="row g-3">
                                <FormField class="col-12" label="Deal title" required :error="errors.deal_title">
                                    <input v-model="form.deal_title" type="text" required class="form-control" :class="{ 'is-invalid': errors.deal_title }" />
                                </FormField>
                                <FormField class="col-md-6" :label="`Amount (${lead.currency})`" :error="errors.deal_amount">
                                    <input v-model="form.deal_amount" type="number" min="0" step="0.01" class="form-control" :class="{ 'is-invalid': errors.deal_amount }" />
                                </FormField>
                                <FormField class="col-md-6" label="Expected close" :error="errors.expected_close_date">
                                    <input v-model="form.expected_close_date" type="date" class="form-control" :class="{ 'is-invalid': errors.expected_close_date }" />
                                </FormField>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" :disabled="loading" @click="$emit('cancel')">Cancel</button>
                            <button class="btn btn-success" :disabled="loading">
                                <span v-if="loading" class="spinner-border spinner-border-sm me-1"></span>
                                <i v-else class="bi bi-arrow-right-circle me-1"></i>Convert
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            <div class="modal-backdrop show"></div>
        </div>
    </Teleport>
</template>
