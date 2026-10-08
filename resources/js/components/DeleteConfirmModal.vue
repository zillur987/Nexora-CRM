<script setup>
import { nextTick, ref, watch } from 'vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    title: { type: String, default: 'Delete item' },
    message: { type: String, default: '' },
    /** Shown under the message. Pass an empty string to hide it. */
    hint: { type: String, default: "This action can't be undone." },
    confirmLabel: { type: String, default: 'Delete' },
    cancelLabel: { type: String, default: 'Cancel' },
    loading: { type: Boolean, default: false },
});
const emit = defineEmits(['confirm', 'cancel']);

const cancelBtn = ref(null);

// Cancel is focused first, so Enter never deletes by accident.
watch(
    () => props.show,
    (open) => open && nextTick(() => cancelBtn.value?.focus()),
);

// Block dismissal while the request is in flight.
function cancel() {
    if (!props.loading) emit('cancel');
}
</script>

<template>
    <Teleport to="body">
        <Transition name="dcm">
            <div v-if="show" class="dcm-backdrop" @click.self="cancel" @keydown.esc="cancel">
                <div
                    class="dcm"
                    role="alertdialog"
                    aria-modal="true"
                    aria-labelledby="dcm-title"
                    aria-describedby="dcm-desc"
                >
                    <div class="dcm__icon" aria-hidden="true">
                        <i class="bi bi-trash3"></i>
                    </div>

                    <div class="dcm__content">
                        <h2 id="dcm-title" class="dcm__title">{{ title }}</h2>
                        <p id="dcm-desc" class="dcm__message">
                            <slot>{{ message }}</slot>
                        </p>
                        <p v-if="hint" class="dcm__hint">{{ hint }}</p>
                    </div>

                    <div class="dcm__actions">
                        <button ref="cancelBtn" type="button" class="dcm__btn dcm__btn--ghost" :disabled="loading" @click="cancel">
                            {{ cancelLabel }}
                        </button>
                        <button type="button" class="dcm__btn dcm__btn--danger" :disabled="loading" @click="emit('confirm')">
                            <span v-if="loading" class="spinner-border spinner-border-sm" aria-hidden="true"></span>
                            {{ loading ? 'Deleting…' : confirmLabel }}
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.dcm-backdrop {
    position: fixed;
    inset: 0;
    z-index: 1080; /* above the lead drawer (1050) and row menu (1060) */
    display: grid;
    place-items: center;
    padding: 1rem;
    background: rgba(16, 24, 40, 0.55);
    backdrop-filter: blur(2px);
}

.dcm {
    --dcm-danger: #d92d20;
    --dcm-danger-hover: #b42318;
    --dcm-danger-soft: #fee4e2;

    width: min(26rem, 100%);
    padding: 1.5rem;
    font-family: 'Inter', system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
    font-size: 0.875rem;
    line-height: 1.5;
    color: #101828;
    text-align: center;
    background: #fff;
    border-radius: 1rem;
    box-shadow: 0 20px 40px -8px rgba(16, 24, 40, 0.25);
}

.dcm__icon {
    display: grid;
    place-items: center;
    width: 3rem;
    height: 3rem;
    margin: 0.375rem auto 1rem;
    font-size: 1.25rem;
    color: var(--dcm-danger);
    background: var(--dcm-danger-soft);
    border-radius: 50%;
    /* Outer ring gives the icon a soft halo without an extra element. */
    box-shadow: 0 0 0 0.375rem #fef3f2;
}

.dcm__title {
    margin: 0.5rem 0 0.25rem;
    font-size: 1.125rem;
    font-weight: 600;
    letter-spacing: -0.01em;
}

.dcm__message {
    margin: 0;
    color: #344054;
    overflow-wrap: anywhere;
}

.dcm__hint {
    margin: 0.25rem 0 0;
    color: #667085;
}

.dcm__actions {
    display: flex;
    gap: 0.75rem;
    margin-top: 1.5rem;
}

.dcm__btn {
    display: inline-flex;
    flex: 1;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    min-height: 2.5rem;
    padding: 0 1rem;
    font: inherit;
    font-weight: 500;
    cursor: pointer;
    border: 1px solid transparent;
    border-radius: 0.5rem;
    transition: background-color 0.15s, border-color 0.15s;
}

.dcm__btn:focus-visible {
    outline: 2px solid #98a2b3;
    outline-offset: 2px;
}

.dcm__btn:disabled {
    cursor: not-allowed;
    opacity: 0.6;
}

.dcm__btn--ghost {
    color: #344054;
    background: #fff;
    border-color: #d0d5dd;
}

.dcm__btn--ghost:hover:not(:disabled) {
    background: #f9fafb;
}

.dcm__btn--danger {
    color: #fff;
    background: var(--dcm-danger);
}

.dcm__btn--danger:hover:not(:disabled) {
    background: var(--dcm-danger-hover);
}

.dcm__btn--danger:focus-visible {
    outline-color: var(--dcm-danger);
}

/* Open/close: fade the backdrop, lift the dialog slightly. */
.dcm-enter-active,
.dcm-leave-active {
    transition: opacity 0.18s ease;
}

.dcm-enter-active .dcm,
.dcm-leave-active .dcm {
    transition: transform 0.18s ease;
}

.dcm-enter-from,
.dcm-leave-to {
    opacity: 0;
}

.dcm-enter-from .dcm,
.dcm-leave-to .dcm {
    transform: translateY(0.5rem) scale(0.98);
}

@media (prefers-reduced-motion: reduce) {
    .dcm-enter-active,
    .dcm-leave-active,
    .dcm-enter-active .dcm,
    .dcm-leave-active .dcm {
        transition: none;
    }
}

@media (max-width: 400px) {
    .dcm__actions {
        flex-direction: column-reverse;
    }
}
</style>