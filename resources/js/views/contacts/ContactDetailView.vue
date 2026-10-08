<script setup>
import { computed, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import { contactsApi } from '@/api/contacts';
import { useToastStore } from '@/stores/toast';
import { parseApiError } from '@/utils/errors';
import { formatDateOnly } from '@/utils/dateOnly';
import { displayUrl, formatDate } from '@/utils/format';
import DeleteConfirmModal from '@/components/DeleteConfirmModal.vue';
import LoadingBlock from '@/components/LoadingBlock.vue';

const props = defineProps({ id: { type: String, required: true } });

const router = useRouter();
const toast = useToastStore();

/* -------------------------------------------------------------------------- */
/* State                                                                      */
/* -------------------------------------------------------------------------- */

const contact = ref(null);
const busy = ref(false);
const confirmingDelete = ref(false);

watch(
    () => props.id,
    async (id) => {
        contact.value = null;
        try {
            contact.value = await contactsApi.get(id);
        } catch {
            toast.error('Contact not found.');
            router.replace({ name: 'contacts.index' });
        }
    },
    { immediate: true },
);

/* -------------------------------------------------------------------------- */
/* Presentation (all derived from `contact`, so the template stays declarative) */
/* -------------------------------------------------------------------------- */

const dash = (value) => value || '—';
const date = (value) => (value ? formatDate(value, true) : '—');

const initials = computed(() =>
    [contact.value?.first_name, contact.value?.last_name]
        .filter(Boolean)
        .map((part) => part[0].toUpperCase())
        .join(''),
);

const subtitle = computed(() => {
    const { job_title, company } = contact.value;
    return [job_title, company?.name].filter(Boolean).join(' at ') || 'No work details';
});

// Add a row here and it renders; no template changes needed.
const contactRows = computed(() => {
    const c = contact.value;
    return [
        { key: 'email', icon: 'bi-envelope', label: 'Email', value: c.email, href: c.email ? `mailto:${c.email}` : null },
        { key: 'phone', icon: 'bi-telephone', label: 'Phone number', value: c.phone, href: c.phone ? `tel:${c.phone}` : null },
        { key: 'linkedin', icon: 'bi-linkedin', label: 'LinkedIn', value: displayUrl(c.linkedin), href: c.linkedin, external: true },
        { key: 'twitter', icon: 'bi-twitter-x', label: 'Twitter / X', value: displayUrl(c.twitter), href: c.twitter, external: true },
    ];
});

const detailRows = computed(() => {
    const c = contact.value;
    return [
        { key: 'first_name', label: 'First name', value: dash(c.first_name) },
        { key: 'last_name', label: 'Last name', value: dash(c.last_name) },
        { key: 'owner', label: 'Contact owner', value: c.owner?.name ?? 'Unassigned' },
        { key: 'birthday', label: 'Birthday', value: formatDateOnly(c.birthday) },
        { key: 'job_title', label: 'Job title', value: dash(c.job_title) },
        { key: 'department', label: 'Department', value: dash(c.department) },
        { key: 'industry', label: 'Industry', value: dash(c.industry?.name) },
        { key: 'source', label: 'Contact source', value: dash(c.source?.name) },
        { key: 'stage', label: 'Contact stage', value: dash(c.stage?.name) },
    ];
});

const addresses = computed(() => [
    { key: 'present', icon: 'bi-house', title: 'Present address', value: contact.value.present_full_address },
    { key: 'permanent', icon: 'bi-geo-alt', title: 'Permanent address', value: contact.value.permanent_full_address },
]);

const activityRows = computed(() => [
    { key: 'created', label: 'Created', value: date(contact.value.created_at) },
    { key: 'updated', label: 'Last updated', value: date(contact.value.updated_at) },
]);

/* -------------------------------------------------------------------------- */
/* Actions                                                                    */
/* -------------------------------------------------------------------------- */

async function remove() {
    busy.value = true;
    try {
        await contactsApi.remove(props.id);
        toast.success('Contact deleted.');
        router.push({ name: 'contacts.index' });
    } catch (e) {
        toast.error(parseApiError(e).message);
        confirmingDelete.value = false;
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <LoadingBlock v-if="!contact" />

    <div v-else class="contact-view">
        <!-- Breadcrumb -->
        <nav class="contact-view__crumbs" aria-label="Breadcrumb">
            <RouterLink :to="{ name: 'contacts.index' }">Contacts</RouterLink>
            <i class="bi bi-chevron-right" aria-hidden="true"></i>
            <span aria-current="page">{{ contact.full_name }}</span>
        </nav>

        <!-- Header -->
        <header class="contact-view__header">
            <div class="contact-view__identity">
                <div class="avatar" aria-hidden="true">{{ initials }}</div>
                <div class="min-w-0">
                    <div class="d-flex align-items-center flex-wrap gap-2">
                        <h1 class="contact-view__name">{{ contact.full_name }}</h1>
                        <span v-if="contact.stage" class="badge text-bg-primary">{{ contact.stage.name }}</span>
                    </div>
                    <p class="contact-view__subtitle">{{ subtitle }}</p>
                </div>
            </div>

            <div class="contact-view__actions">
                <RouterLink :to="{ name: 'contacts.edit', params: { id } }" class="btn btn-outline-secondary">
                    <i class="bi bi-pencil me-2"></i>Edit
                </RouterLink>
                <button type="button" class="btn btn-outline-danger" :disabled="busy" @click="confirmingDelete = true">
                    <i class="bi bi-trash me-2"></i>Delete
                </button>
            </div>
        </header>

        <!-- Key facts -->
        <section class="panel facts" aria-label="Key facts">
            <div class="facts__cell">
                <span class="field-label">Company</span>
                <strong class="facts__value">
                    <RouterLink v-if="contact.company" :to="{ name: 'companies.show', params: { id: contact.company.id } }" class="rows__link">
                        {{ contact.company.name }}
                    </RouterLink>
                    <template v-else>—</template>
                </strong>
            </div>
            <div class="facts__cell">
                <span class="field-label">Job title</span>
                <strong class="facts__value">{{ dash(contact.job_title) }}</strong>
            </div>
            <div class="facts__cell">
                <span class="field-label">Contact stage</span>
                <strong class="facts__value">{{ dash(contact.stage?.name) }}</strong>
            </div>
            <div class="facts__cell">
                <span class="field-label">Owner</span>
                <strong class="facts__value">{{ contact.owner?.name ?? 'Unassigned' }}</strong>
            </div>
        </section>

        <!-- Body -->
        <div class="contact-view__grid">
            <main class="contact-view__main">
                <section class="panel">
                    <header class="panel__head"><h2 class="panel__title">Contact information</h2></header>
                    <dl class="info">
                        <div v-for="row in detailRows" :key="row.key" class="info__item">
                            <dt class="field-label">{{ row.label }}</dt>
                            <dd class="info__value">{{ row.value }}</dd>
                        </div>

                        <div class="info__item">
                            <dt class="field-label">Company</dt>
                            <dd class="info__value">
                                <RouterLink v-if="contact.company" :to="{ name: 'companies.show', params: { id: contact.company.id } }" class="rows__link">
                                    {{ contact.company.name }}
                                </RouterLink>
                                <template v-else>—</template>
                            </dd>
                        </div>
                    </dl>
                </section>

                <section class="panel">
                    <header class="panel__head"><h2 class="panel__title">Addresses</h2></header>
                    <div class="addresses">
                        <div v-for="a in addresses" :key="a.key" class="addresses__item">
                            <span class="field-label"><i class="bi me-2" :class="a.icon" aria-hidden="true"></i>{{ a.title }}</span>
                            <p class="addresses__value">{{ dash(a.value) }}</p>
                        </div>
                    </div>
                </section>

                <section class="panel">
                    <header class="panel__head"><h2 class="panel__title">Description</h2></header>
                    <div class="panel__body">
                        <p v-if="contact.description" class="notes">{{ contact.description }}</p>
                        <p v-else class="empty">No description yet. Add context by editing this contact.</p>
                    </div>
                </section>
            </main>

            <aside class="contact-view__side">
                <section class="panel">
                    <header class="panel__head"><h2 class="panel__title">Contact details</h2></header>
                    <ul class="rows">
                        <li v-for="row in contactRows" :key="row.key" class="rows__item">
                            <i class="bi rows__icon" :class="row.icon" aria-hidden="true"></i>
                            <div class="min-w-0">
                                <span class="field-label">{{ row.label }}</span>
                                <a
                                    v-if="row.href && row.value"
                                    :href="row.href"
                                    class="rows__value rows__link"
                                    :target="row.external ? '_blank' : null"
                                    :rel="row.external ? 'noopener noreferrer' : null"
                                >
                                    {{ row.value }}
                                </a>
                                <span v-else class="rows__value">{{ dash(row.value) }}</span>
                            </div>
                        </li>
                    </ul>
                </section>

                <section class="panel">
                    <header class="panel__head"><h2 class="panel__title">Activity</h2></header>
                    <dl class="activity">
                        <div v-for="row in activityRows" :key="row.key" class="activity__item">
                            <dt>{{ row.label }}</dt>
                            <dd>{{ row.value }}</dd>
                        </div>
                    </dl>
                </section>
            </aside>
        </div>

        <DeleteConfirmModal
            :show="confirmingDelete"
            title="Delete contact"
            :message="`Delete ${contact.full_name}?`"
            :loading="busy"
            @confirm="remove"
            @cancel="confirmingDelete = false"
        />
    </div>
</template>

<style scoped>
/* Same structure as the company detail page; every value comes from the global --crm-* tokens. */
.contact-view {
    --cv-gap: 1.25rem;
    --cv-radius: 0.75rem;

    display: flex;
    flex-direction: column;
    gap: var(--cv-gap);
    font-family: var(--crm-font);
    font-size: var(--crm-fs);
    line-height: 1.5;
    color: var(--crm-text);
    -webkit-font-smoothing: antialiased;
}

.min-w-0 {
    min-width: 0;
}

/* Breadcrumb */
.contact-view__crumbs {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: var(--crm-fs-sm);
    color: var(--crm-text-muted);
}

.contact-view__crumbs a {
    color: inherit;
    text-decoration: none;
}

.contact-view__crumbs a:hover {
    color: var(--crm-primary);
}

.contact-view__crumbs i {
    font-size: 0.625rem;
}

.contact-view__crumbs [aria-current] {
    font-weight: 500;
    color: var(--crm-text-strong);
}

/* Header */
.contact-view__header {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
}

.contact-view__identity {
    display: flex;
    align-items: center;
    gap: 1rem;
    min-width: 0;
}

.avatar {
    display: grid;
    flex: none;
    place-items: center;
    width: 3.5rem;
    height: 3.5rem;
    font-size: 1.125rem;
    font-weight: 600;
    color: var(--crm-primary);
    background: var(--crm-primary-soft);
    border-radius: 0.75rem;
}

.contact-view__name {
    margin: 0;
    font-size: 1.5rem;
    font-weight: 600;
    letter-spacing: -0.02em;
    line-height: 1.25;
}

.contact-view__subtitle {
    margin: 0.125rem 0 0;
    color: var(--crm-text-muted);
}

.contact-view__actions {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.contact-view__actions .btn {
    --bs-btn-font-size: var(--crm-fs);
    --bs-btn-font-weight: 500;
    --bs-btn-border-radius: var(--crm-radius);
    display: inline-flex;
    align-items: center;
    min-height: var(--crm-control-h);
}

/* Panel: the one container pattern used across the page */
.panel {
    background: var(--crm-surface);
    border: 1px solid var(--crm-border);
    border-radius: var(--cv-radius);
}

.panel__head {
    padding: 0.875rem 1.25rem;
    border-bottom: 1px solid var(--crm-border);
}

.panel__title {
    margin: 0;
    font-size: 0.9375rem;
    font-weight: 600;
}

.panel__body {
    padding: 1.25rem;
}

.field-label {
    display: block;
    font-size: var(--crm-fs-sm);
    font-weight: 500;
    color: var(--crm-text-muted);
}

/* Key facts strip */
.facts {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(10rem, 1fr));
}

.facts__cell {
    display: flex;
    flex-direction: column;
    gap: 0.375rem;
    padding: 1rem 1.25rem;
}

.facts__cell + .facts__cell {
    border-left: 1px solid var(--crm-border);
}

.facts__value {
    font-size: 1.125rem;
    font-weight: 600;
    font-variant-numeric: tabular-nums;
    line-height: 1.3;
}

/* Layout */
.contact-view__grid {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 20rem;
    gap: var(--cv-gap);
    align-items: start;
}

.contact-view__main,
.contact-view__side {
    display: flex;
    flex-direction: column;
    gap: var(--cv-gap);
    min-width: 0;
}

/* Company information grid */
.info {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(12rem, 1fr));
    gap: 1.25rem 1.5rem;
    padding: 1.25rem;
    margin: 0;
}

.info__value {
    margin: 0.125rem 0 0;
    font-weight: 500;
    overflow-wrap: anywhere;
}

/* Addresses */
.addresses {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(14rem, 1fr));
    gap: 1.25rem 1.5rem;
    padding: 1.25rem;
}

.addresses__value {
    margin: 0.25rem 0 0;
    font-weight: 500;
    overflow-wrap: anywhere;
}

/* Description */
.notes {
    max-width: 70ch;
    margin: 0;
    color: var(--crm-text-strong);
    white-space: pre-wrap;
    overflow-wrap: anywhere;
}

.empty {
    margin: 0;
    color: var(--crm-text-muted);
}

/* Contact rows */
.rows {
    padding: 0.5rem 0;
    margin: 0;
    list-style: none;
}

.rows__item {
    display: flex;
    gap: 0.75rem;
    padding: 0.625rem 1.25rem;
}

.rows__icon {
    flex: none;
    margin-top: 0.125rem;
    color: var(--crm-text-muted);
}

.rows__value {
    display: block;
    overflow-wrap: anywhere;
}

.rows__link {
    color: var(--crm-text);
    text-decoration: none;
}

.rows__link:hover {
    color: var(--crm-primary);
    text-decoration: underline;
}

/* Activity */
.activity {
    padding: 0.5rem 0;
    margin: 0;
}

.activity__item {
    display: flex;
    justify-content: space-between;
    gap: 1rem;
    padding: 0.5rem 1.25rem;
}

.activity__item dt {
    font-weight: 400;
    color: var(--crm-text-muted);
}

.activity__item dd {
    margin: 0;
    font-weight: 500;
    text-align: right;
    font-variant-numeric: tabular-nums;
}

/* Responsive */
@media (max-width: 991.98px) {
    .contact-view__grid {
        grid-template-columns: minmax(0, 1fr);
    }
}

@media (max-width: 575.98px) {
    .facts__cell + .facts__cell {
        border-left: 0;
        border-top: 1px solid var(--crm-border);
    }

    .contact-view__actions {
        width: 100%;
    }
}
</style>
