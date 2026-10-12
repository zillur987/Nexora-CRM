<script setup>
import { useRoute } from 'vue-router';

const route = useRoute();
</script>

<template>
    <div class="card forbidden-page rounded-3">
        <div class="card-body forbidden-page__body">
            <span class="forbidden-page__icon" aria-hidden="true"><i class="bi bi-shield-lock"></i></span>
            <h1 class="forbidden-page__title">You don't have access to this page</h1>
            <p class="forbidden-page__text">
                Your role doesn't include the permission needed here. If you think this is a mistake, ask an administrator to update your access.
            </p>
            <p v-if="route.query.from" class="forbidden-page__path">{{ route.query.from }}</p>
            <div class="d-flex justify-content-center gap-2 mt-3">
                <button type="button" class="btn btn-outline-secondary" @click="$router.back()">
                    <i class="bi bi-arrow-left me-2"></i>Go back
                </button>
                <RouterLink to="/" class="btn btn-primary">Dashboard</RouterLink>
            </div>
        </div>
    </div>
</template>

<style scoped>
.forbidden-page {
    font-family: var(--crm-font);
    font-size: var(--crm-fs);
    color: var(--crm-text);
}

.forbidden-page__body {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 4rem 1.5rem;
    text-align: center;
}

.forbidden-page__icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 4rem;
    height: 4rem;
    margin-bottom: 1.25rem;
    font-size: 1.75rem;
    color: var(--crm-primary);
    background: var(--crm-primary-soft);
    border-radius: 50%;
}

.forbidden-page__title {
    margin: 0 0 0.5rem;
    font-size: 1.5rem;
    font-weight: 600;
    letter-spacing: -0.02em;
}

.forbidden-page__text {
    max-width: 30rem;
    margin: 0;
    color: var(--crm-text-muted);
}

.forbidden-page__path {
    margin: 0.75rem 0 0;
    font-size: var(--crm-fs-sm);
    color: var(--crm-text-muted);
}

.forbidden-page .btn {
    display: inline-flex;
    align-items: center;
    min-height: var(--crm-control-h);
    font-size: var(--crm-fs);
    font-weight: 500;
    border-radius: var(--crm-radius);
}
</style>
