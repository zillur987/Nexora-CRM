<script setup>
import { nextTick, onBeforeUnmount, ref, watch } from 'vue';

let uid = 0;

const props = defineProps({
    show: Boolean,
    title: { type: String, required: true },
    subtitle: { type: String, default: '' },
    width: { type: String, default: '36rem' },
});
const emit = defineEmits(['close']);

const titleId = `slideover-title-${++uid}`;
const panel = ref(null);

let previouslyFocused = null;
let previousOverflow = '';

const FOCUSABLE =
    'a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

/** Esc closes; Tab stays inside the panel while it is open. */
function onKeydown(event) {
    if (event.key === 'Escape') {
        event.stopPropagation();
        emit('close');
        return;
    }
    if (event.key !== 'Tab' || !panel.value) return;

    const items = [...panel.value.querySelectorAll(FOCUSABLE)].filter((el) => el.offsetParent !== null);
    if (!items.length) return;

    const first = items[0];
    const last = items[items.length - 1];
    if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
    }
}

function release() {
    document.body.style.overflow = previousOverflow;
    previouslyFocused?.focus?.();
    previouslyFocused = null;
}

watch(
    () => props.show,
    async (open) => {
        if (!open) return release();

        previouslyFocused = document.activeElement;
        previousOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';

        await nextTick();
        const firstField = panel.value?.querySelector(
            '.slideover__body input:not([type="hidden"]):not(.visually-hidden), .slideover__body select, .slideover__body textarea',
        );
        (firstField ?? panel.value)?.focus();
    },
    { immediate: true },
);

onBeforeUnmount(() => {
    if (props.show) release();
});
</script>

<template>
    <Teleport to="body">
        <Transition name="slideover">
            <div v-if="show" class="slideover" @keydown="onKeydown">
                <div class="slideover__backdrop" @click="emit('close')"></div>

                <div
                    ref="panel"
                    class="slideover__panel"
                    role="dialog"
                    aria-modal="true"
                    :aria-labelledby="titleId"
                    :style="{ '--so-width': width }"
                    tabindex="-1"
                >
                    <header class="slideover__header">
                        <button type="button" class="slideover__close" aria-label="Close" @click="emit('close')">
                            <i class="bi bi-x-lg"></i>
                        </button>
                        <div class="slideover__heading">
                            <h2 :id="titleId" class="slideover__title">{{ title }}</h2>
                            <p v-if="subtitle" class="slideover__subtitle">{{ subtitle }}</p>
                        </div>
                    </header>

                    <div class="slideover__body">
                        <slot />
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
/* Sits above the sidebar (1040) and below Bootstrap-style modals (1055), so a confirm dialog can open over it. */
.slideover {
    position: fixed;
    inset: 0;
    z-index: 1045;
}

.slideover__backdrop {
    position: absolute;
    inset: 0;
    background: rgb(16 24 40 / 0.45);
}

.slideover__panel {
    position: absolute;
    top: 0;
    right: 0;
    bottom: 0;
    display: flex;
    flex-direction: column;
    width: min(100%, var(--so-width));
    background: var(--crm-surface, #fff);
    box-shadow: -12px 0 32px -12px rgb(16 24 40 / 0.25);
    outline: 0;
}

.slideover__header {
    display: flex;
    flex: none;
    align-items: center;
    gap: 0.75rem;
    padding: 1rem 1.5rem;
    border-bottom: 1px solid var(--crm-border, #e4e7ec);
}

.slideover__heading {
    flex: 1 1 auto;
    min-width: 0;
}

.slideover__title {
    margin: 0;
    font-size: 1.125rem;
    font-weight: 600;
    letter-spacing: -0.01em;
    color: var(--crm-text, #101828);
}

.slideover__subtitle {
    margin: 0.125rem 0 0;
    font-size: 0.8125rem;
    color: var(--crm-text-muted, #667085);
}

.slideover__close {
    display: inline-grid;
    flex: none;
    place-items: center;
    width: 2rem;
    height: 2rem;
    padding: 0;
    color: var(--crm-text-muted, #667085);
    background: transparent;
    border: 0;
    border-radius: 0.5rem;
    cursor: pointer;
}

.slideover__close:hover {
    color: var(--crm-text, #101828);
    background: var(--crm-hover, #f2f4f7);
}

.slideover__body {
    display: flex;
    flex: 1 1 auto;
    flex-direction: column;
    min-height: 0;
}

/* Slide in from the right, backdrop fades */
.slideover-enter-active,
.slideover-leave-active {
    transition: opacity 0.2s ease;
}

.slideover-enter-active .slideover__panel,
.slideover-leave-active .slideover__panel {
    transition: transform 0.25s cubic-bezier(0.32, 0.72, 0, 1);
}

.slideover-enter-from,
.slideover-leave-to {
    opacity: 0;
}

.slideover-enter-from .slideover__panel,
.slideover-leave-to .slideover__panel {
    transform: translateX(100%);
}

@media (prefers-reduced-motion: reduce) {
    .slideover-enter-active,
    .slideover-leave-active,
    .slideover-enter-active .slideover__panel,
    .slideover-leave-active .slideover__panel {
        transition: none;
    }
}
</style>