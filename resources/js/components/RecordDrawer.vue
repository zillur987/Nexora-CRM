<script setup>
import { onBeforeUnmount, watch } from 'vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    title: { type: String, required: true },
});
const emit = defineEmits(['close']);

const onKeydown = (event) => {
    if (event.key === 'Escape') emit('close');
};

// Only listen while open.
watch(
    () => props.show,
    (open) => document[open ? 'addEventListener' : 'removeEventListener']('keydown', onKeydown),
    { immediate: true },
);
onBeforeUnmount(() => document.removeEventListener('keydown', onKeydown));
</script>

<template>
    <Teleport to="body">
        <Transition name="drawer-fade">
            <div v-if="show" class="record-drawer-backdrop" @click="emit('close')"></div>
        </Transition>

        <Transition name="drawer-slide">
            <aside v-if="show" class="record-drawer" role="dialog" aria-modal="true" :aria-label="title" @click.stop>
                <header class="record-drawer__header">
                    <button type="button" class="record-drawer__close" aria-label="Close" @click="emit('close')">
                        <i class="bi bi-x-lg"></i>
                    </button>
                    <h2 class="record-drawer__title">{{ title }}</h2>
                </header>

                <!-- Only this area scrolls -->
                <div class="record-drawer__body">
                    <slot />
                </div>
            </aside>
        </Transition>
    </Teleport>
</template>

<style scoped>
.record-drawer-backdrop {
    position: fixed;
    inset: 0;
    z-index: 1040;
    background: rgba(0, 0, 0, 0.48);
}

.record-drawer {
    position: fixed;
    inset: 0 0 0 auto;
    z-index: 1050;
    display: flex;
    flex-direction: column;
    width: min(760px, 52vw);
    min-width: 520px;
    overflow: hidden;
    background: var(--crm-surface, #fff);
    box-shadow: -8px 0 30px rgba(0, 0, 0, 0.12);
}

.record-drawer__header {
    display: flex;
    flex: 0 0 72px;
    align-items: center;
    gap: 1rem;
    height: 72px;
    padding: 0 28px;
    border-bottom: 1px solid var(--crm-border, #e4e7ec);
}

.record-drawer__title {
    margin: 0;
    font-size: 1.375rem;
    font-weight: 600;
    color: #1f2937;
}

.record-drawer__close {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 38px;
    height: 38px;
    font-size: 1.25rem;
    color: #6b7280;
    cursor: pointer;
    background: transparent;
    border: 0;
    border-radius: 8px;
    transition: background-color 0.15s ease, color 0.15s ease;
}

.record-drawer__close:hover {
    color: #111827;
    background: #f3f4f6;
}

.record-drawer__body {
    flex: 1 1 auto;
    min-height: 0;
    padding: 28px;
    overflow: hidden auto;
    overscroll-behavior: contain;
}

.drawer-fade-enter-active,
.drawer-fade-leave-active {
    transition: opacity 0.25s ease;
}

.drawer-fade-enter-from,
.drawer-fade-leave-to {
    opacity: 0;
}

.drawer-slide-enter-active,
.drawer-slide-leave-active {
    transition: transform 0.28s ease;
}

.drawer-slide-enter-from,
.drawer-slide-leave-to {
    transform: translateX(100%);
}

@media (prefers-reduced-motion: reduce) {
    .drawer-fade-enter-active,
    .drawer-fade-leave-active,
    .drawer-slide-enter-active,
    .drawer-slide-leave-active {
        transition: none;
    }
}

@media (max-width: 768px) {
    .record-drawer {
        width: 100%;
        min-width: 0;
    }

    .record-drawer__header {
        padding: 0 20px;
    }

    .record-drawer__body {
        padding: 20px;
    }
}
</style>
