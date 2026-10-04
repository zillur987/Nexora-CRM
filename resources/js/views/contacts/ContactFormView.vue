<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { useRouter } from 'vue-router';

import { contactsApi } from '@/api/contacts';
import { CONTACT_STATUSES } from '@/constants';
import { useToastStore } from '@/stores/toast';
import { parseApiError } from '@/utils/errors';

import FormField from '@/components/FormField.vue';
import LoadingBlock from '@/components/LoadingBlock.vue';

const props = defineProps({
    id: {
        type: String,
        default: null,
    },
});

const router = useRouter();
const toast = useToastStore();

const isEdit = computed(() => Boolean(props.id));

/*
|--------------------------------------------------------------------------
| Form
|--------------------------------------------------------------------------
*/

const form = reactive({
    // Personal information
    first_name: '',
    last_name: '',
    email: '',
    phone: '',
    mobile: '',
    job_title: '',
    department: '',
    date_of_birth: '',

    // Company
    company: '',
    website: '',
    industry: '',
    company_size: '',

    // Address
    address: '',
    city: '',
    state: '',
    postal_code: '',
    country: '',

    // CRM
    status: 'lead',
    contact_type: 'individual',
    source: '',
    owner_id: null,

    // Additional
    notes: '',
});

const errors = ref({});

const loading = ref(isEdit.value);
const saving = ref(false);

/*
|--------------------------------------------------------------------------
| Options
|--------------------------------------------------------------------------
*/

const CONTACT_TYPES = [
    {
        value: 'individual',
        label: 'Individual',
    },
    {
        value: 'business',
        label: 'Business',
    },
];

const CONTACT_SOURCES = [
    {
        value: 'website',
        label: 'Website',
    },
    {
        value: 'referral',
        label: 'Referral',
    },
    {
        value: 'social_media',
        label: 'Social media',
    },
    {
        value: 'email',
        label: 'Email',
    },
    {
        value: 'phone',
        label: 'Phone',
    },
    {
        value: 'advertisement',
        label: 'Advertisement',
    },
    {
        value: 'event',
        label: 'Event',
    },
    {
        value: 'other',
        label: 'Other',
    },
];

const COMPANY_SIZES = [
    {
        value: '1-10',
        label: '1–10 employees',
    },
    {
        value: '11-50',
        label: '11–50 employees',
    },
    {
        value: '51-200',
        label: '51–200 employees',
    },
    {
        value: '201-500',
        label: '201–500 employees',
    },
    {
        value: '501-1000',
        label: '501–1,000 employees',
    },
    {
        value: '1000+',
        label: '1,000+ employees',
    },
];

const INDUSTRIES = [
    'Technology',
    'Software',
    'Finance',
    'Banking',
    'Healthcare',
    'Education',
    'Real Estate',
    'Retail',
    'Manufacturing',
    'Construction',
    'Telecommunications',
    'Marketing',
    'Consulting',
    'Government',
    'Other',
];

/*
|--------------------------------------------------------------------------
| Load contact
|--------------------------------------------------------------------------
*/

onMounted(async () => {
    if (!isEdit.value) {
        return;
    }

    try {
        const contact = await contactsApi.get(props.id);

        Object.assign(form, {
            first_name: contact.first_name ?? '',
            last_name: contact.last_name ?? '',
            email: contact.email ?? '',
            phone: contact.phone ?? '',
            mobile: contact.mobile ?? '',
            job_title: contact.job_title ?? '',
            department: contact.department ?? '',
            date_of_birth: contact.date_of_birth ?? '',

            company: contact.company ?? '',
            website: contact.website ?? '',
            industry: contact.industry ?? '',
            company_size: contact.company_size ?? '',

            address: contact.address ?? '',
            city: contact.city ?? '',
            state: contact.state ?? '',
            postal_code: contact.postal_code ?? '',
            country: contact.country ?? '',

            status: contact.status ?? 'lead',
            contact_type: contact.contact_type ?? 'individual',
            source: contact.source ?? '',
            owner_id: contact.owner_id ?? null,

            notes: contact.notes ?? '',
        });
    } catch (error) {
        toast.error(
            parseApiError(error).message || 'Contact not found.'
        );

        router.replace({
            name: 'contacts.index',
        });
    } finally {
        loading.value = false;
    }
});

/*
|--------------------------------------------------------------------------
| Submit
|--------------------------------------------------------------------------
*/

async function submit() {
    saving.value = true;
    errors.value = {};

    try {
        const payload = {
            ...form,
        };

        const saved = isEdit.value
            ? await contactsApi.update(props.id, payload)
            : await contactsApi.create(payload);

        toast.success(
            isEdit.value
                ? 'Contact updated successfully.'
                : 'Contact created successfully.'
        );

        router.push({
            name: 'contacts.show',
            params: {
                id: saved.id,
            },
        });
    } catch (error) {
        const parsed = parseApiError(error);

        errors.value = parsed.fields || {};

        toast.error(
            parsed.message || 'Unable to save contact.'
        );
    } finally {
        saving.value = false;
    }
}

/*
|--------------------------------------------------------------------------
| Cancel
|--------------------------------------------------------------------------
*/

function cancel() {
    router.push({
        name: 'contacts.index',
    });
}
</script>

<template>
    <div class="contact-form-page">

        <!-- ========================================================= -->
        <!-- PAGE HEADER -->
        <!-- ========================================================= -->

        <div
            class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4"
        >
            <div>
                <div class="d-flex align-items-center gap-2 mb-1">

                    <RouterLink
                        :to="{ name: 'contacts.index' }"
                        class="btn btn-sm btn-light border"
                        title="Back to contacts"
                    >
                        <i class="bi bi-arrow-left"></i>
                    </RouterLink>

                    <h1 class="h3 fw-bold mb-0">
                        {{ isEdit ? 'Edit contact' : 'New contact' }}
                    </h1>

                </div>

                <p class="text-muted mb-0">
                    {{
                        isEdit
                            ? 'Update contact information and CRM details.'
                            : 'Create a new customer, lead or business contact.'
                    }}
                </p>
            </div>

            <div
                v-if="isEdit"
                class="d-flex align-items-center gap-2"
            >
                <span class="text-muted small">
                    Contact ID:
                </span>

                <code class="small">
                    {{ props.id }}
                </code>
            </div>
        </div>

        <!-- ========================================================= -->
        <!-- LOADING -->
        <!-- ========================================================= -->

        <LoadingBlock v-if="loading" />

        <form
            v-else
            @submit.prevent="submit"
        >

            <!-- ===================================================== -->
            <!-- PERSONAL INFORMATION -->
            <!-- ===================================================== -->

            <div class="card border-0 shadow-sm mb-4">

                <div class="card-header bg-white py-3">

                    <div class="d-flex align-items-center gap-3">

                        <div class="section-icon bg-primary-subtle text-primary">
                            <i class="bi bi-person"></i>
                        </div>

                        <div>
                            <h5 class="mb-0 fw-semibold">
                                Personal information
                            </h5>

                            <small class="text-muted">
                                Basic contact details
                            </small>
                        </div>

                    </div>

                </div>

                <div class="card-body">

                    <div class="row g-4">

                        <!-- First name -->
                        <FormField
                            class="col-md-6"
                            label="First name"
                            required
                            :error="errors.first_name"
                        >
                            <div class="input-icon-wrapper">
                                <i class="bi bi-person"></i>

                                <input
                                    v-model="form.first_name"
                                    type="text"
                                    class="form-control"
                                    placeholder="John"
                                    autocomplete="given-name"
                                    :class="{
                                        'is-invalid':
                                            errors.first_name
                                    }"
                                    required
                                />
                            </div>
                        </FormField>

                        <!-- Last name -->
                        <FormField
                            class="col-md-6"
                            label="Last name"
                            required
                            :error="errors.last_name"
                        >
                            <input
                                v-model="form.last_name"
                                type="text"
                                class="form-control"
                                placeholder="Doe"
                                autocomplete="family-name"
                                :class="{
                                    'is-invalid':
                                        errors.last_name
                                }"
                                required
                            />
                        </FormField>

                        <!-- Email -->
                        <FormField
                            class="col-md-6"
                            label="Email"
                            required
                            :error="errors.email"
                        >
                            <div class="input-icon-wrapper">
                                <i class="bi bi-envelope"></i>

                                <input
                                    v-model="form.email"
                                    type="email"
                                    class="form-control"
                                    placeholder="john@example.com"
                                    autocomplete="email"
                                    :class="{
                                        'is-invalid':
                                            errors.email
                                    }"
                                    required
                                />
                            </div>
                        </FormField>

                        <!-- Phone -->
                        <FormField
                            class="col-md-6"
                            label="Phone"
                            :error="errors.phone"
                        >
                            <div class="input-icon-wrapper">
                                <i class="bi bi-telephone"></i>

                                <input
                                    v-model="form.phone"
                                    type="tel"
                                    class="form-control"
                                    placeholder="+880 1XXXXXXXXX"
                                    autocomplete="tel"
                                    :class="{
                                        'is-invalid':
                                            errors.phone
                                    }"
                                />
                            </div>
                        </FormField>

                        <!-- Mobile -->
                        <FormField
                            class="col-md-6"
                            label="Mobile"
                            :error="errors.mobile"
                        >
                            <div class="input-icon-wrapper">
                                <i class="bi bi-phone"></i>

                                <input
                                    v-model="form.mobile"
                                    type="tel"
                                    class="form-control"
                                    placeholder="+880 1XXXXXXXXX"
                                    autocomplete="tel"
                                    :class="{
                                        'is-invalid':
                                            errors.mobile
                                    }"
                                />
                            </div>
                        </FormField>

                        <!-- Job title -->
                        <FormField
                            class="col-md-6"
                            label="Job title"
                            :error="errors.job_title"
                        >
                            <div class="input-icon-wrapper">
                                <i class="bi bi-briefcase"></i>

                                <input
                                    v-model="form.job_title"
                                    type="text"
                                    class="form-control"
                                    placeholder="Sales Manager"
                                    :class="{
                                        'is-invalid':
                                            errors.job_title
                                    }"
                                />
                            </div>
                        </FormField>

                        <!-- Department -->
                        <FormField
                            class="col-md-6"
                            label="Department"
                            :error="errors.department"
                        >
                            <input
                                v-model="form.department"
                                type="text"
                                class="form-control"
                                placeholder="Sales"
                                :class="{
                                    'is-invalid':
                                        errors.department
                                }"
                            />
                        </FormField>

                        <!-- Date of birth -->
                        <FormField
                            class="col-md-6"
                            label="Date of birth"
                            :error="errors.date_of_birth"
                        >
                            <input
                                v-model="form.date_of_birth"
                                type="date"
                                class="form-control"
                                :class="{
                                    'is-invalid':
                                        errors.date_of_birth
                                }"
                            />
                        </FormField>

                    </div>

                </div>
            </div>

            <!-- ===================================================== -->
            <!-- COMPANY -->
            <!-- ===================================================== -->

            <div class="card border-0 shadow-sm mb-4">

                <div class="card-header bg-white py-3">

                    <div class="d-flex align-items-center gap-3">

                        <div class="section-icon bg-info-subtle text-info">
                            <i class="bi bi-building"></i>
                        </div>

                        <div>
                            <h5 class="mb-0 fw-semibold">
                                Company information
                            </h5>

                            <small class="text-muted">
                                Organization and professional details
                            </small>
                        </div>

                    </div>

                </div>

                <div class="card-body">

                    <div class="row g-4">

                        <!-- Company -->
                        <FormField
                            class="col-md-6"
                            label="Company"
                            :error="errors.company"
                        >
                            <div class="input-icon-wrapper">
                                <i class="bi bi-building"></i>

                                <input
                                    v-model="form.company"
                                    type="text"
                                    class="form-control"
                                    placeholder="Acme Corporation"
                                    :class="{
                                        'is-invalid':
                                            errors.company
                                    }"
                                />
                            </div>
                        </FormField>

                        <!-- Website -->
                        <FormField
                            class="col-md-6"
                            label="Website"
                            :error="errors.website"
                        >
                            <div class="input-icon-wrapper">
                                <i class="bi bi-globe"></i>

                                <input
                                    v-model="form.website"
                                    type="url"
                                    class="form-control"
                                    placeholder="https://example.com"
                                    :class="{
                                        'is-invalid':
                                            errors.website
                                    }"
                                />
                            </div>
                        </FormField>

                        <!-- Industry -->
                        <FormField
                            class="col-md-6"
                            label="Industry"
                            :error="errors.industry"
                        >
                            <select
                                v-model="form.industry"
                                class="form-select"
                                :class="{
                                    'is-invalid':
                                        errors.industry
                                }"
                            >
                                <option value="">
                                    Select industry
                                </option>

                                <option
                                    v-for="industry in INDUSTRIES"
                                    :key="industry"
                                    :value="industry"
                                >
                                    {{ industry }}
                                </option>
                            </select>
                        </FormField>

                        <!-- Company size -->
                        <FormField
                            class="col-md-6"
                            label="Company size"
                            :error="errors.company_size"
                        >
                            <select
                                v-model="form.company_size"
                                class="form-select"
                                :class="{
                                    'is-invalid':
                                        errors.company_size
                                }"
                            >
                                <option value="">
                                    Select company size
                                </option>

                                <option
                                    v-for="size in COMPANY_SIZES"
                                    :key="size.value"
                                    :value="size.value"
                                >
                                    {{ size.label }}
                                </option>
                            </select>
                        </FormField>

                    </div>

                </div>
            </div>

            <!-- ===================================================== -->
            <!-- ADDRESS -->
            <!-- ===================================================== -->

            <div class="card border-0 shadow-sm mb-4">

                <div class="card-header bg-white py-3">

                    <div class="d-flex align-items-center gap-3">

                        <div class="section-icon bg-success-subtle text-success">
                            <i class="bi bi-geo-alt"></i>
                        </div>

                        <div>
                            <h5 class="mb-0 fw-semibold">
                                Address
                            </h5>

                            <small class="text-muted">
                                Contact location
                            </small>
                        </div>

                    </div>

                </div>

                <div class="card-body">

                    <div class="row g-4">

                        <!-- Address -->
                        <FormField
                            class="col-12"
                            label="Street address"
                            :error="errors.address"
                        >
                            <textarea
                                v-model="form.address"
                                rows="2"
                                class="form-control"
                                placeholder="Street, building, apartment..."
                                :class="{
                                    'is-invalid':
                                        errors.address
                                }"
                            ></textarea>
                        </FormField>

                        <!-- City -->
                        <FormField
                            class="col-md-6"
                            label="City"
                            :error="errors.city"
                        >
                            <input
                                v-model="form.city"
                                type="text"
                                class="form-control"
                                placeholder="Dhaka"
                                :class="{
                                    'is-invalid':
                                        errors.city
                                }"
                            />
                        </FormField>

                        <!-- State -->
                        <FormField
                            class="col-md-6"
                            label="State / Province"
                            :error="errors.state"
                        >
                            <input
                                v-model="form.state"
                                type="text"
                                class="form-control"
                                placeholder="Dhaka Division"
                                :class="{
                                    'is-invalid':
                                        errors.state
                                }"
                            />
                        </FormField>

                        <!-- Postal -->
                        <FormField
                            class="col-md-6"
                            label="Postal code"
                            :error="errors.postal_code"
                        >
                            <input
                                v-model="form.postal_code"
                                type="text"
                                class="form-control"
                                placeholder="1200"
                                :class="{
                                    'is-invalid':
                                        errors.postal_code
                                }"
                            />
                        </FormField>

                        <!-- Country -->
                        <FormField
                            class="col-md-6"
                            label="Country"
                            :error="errors.country"
                        >
                            <input
                                v-model="form.country"
                                type="text"
                                class="form-control"
                                placeholder="Bangladesh"
                                :class="{
                                    'is-invalid':
                                        errors.country
                                }"
                            />
                        </FormField>

                    </div>

                </div>
            </div>

            <!-- ===================================================== -->
            <!-- CRM INFORMATION -->
            <!-- ===================================================== -->

            <div class="card border-0 shadow-sm mb-4">

                <div class="card-header bg-white py-3">

                    <div class="d-flex align-items-center gap-3">

                        <div class="section-icon bg-warning-subtle text-warning">
                            <i class="bi bi-funnel"></i>
                        </div>

                        <div>
                            <h5 class="mb-0 fw-semibold">
                                CRM information
                            </h5>

                            <small class="text-muted">
                                Classification and lead management
                            </small>
                        </div>

                    </div>

                </div>

                <div class="card-body">

                    <div class="row g-4">

                        <!-- Status -->
                        <FormField
                            class="col-md-4"
                            label="Status"
                            required
                            :error="errors.status"
                        >
                            <select
                                v-model="form.status"
                                class="form-select"
                                :class="{
                                    'is-invalid':
                                        errors.status
                                }"
                                required
                            >
                                <option
                                    v-for="status in CONTACT_STATUSES"
                                    :key="status.value"
                                    :value="status.value"
                                >
                                    {{ status.label }}
                                </option>
                            </select>
                        </FormField>

                        <!-- Contact type -->
                        <FormField
                            class="col-md-4"
                            label="Contact type"
                            :error="errors.contact_type"
                        >
                            <select
                                v-model="form.contact_type"
                                class="form-select"
                                :class="{
                                    'is-invalid':
                                        errors.contact_type
                                }"
                            >
                                <option
                                    v-for="type in CONTACT_TYPES"
                                    :key="type.value"
                                    :value="type.value"
                                >
                                    {{ type.label }}
                                </option>
                            </select>
                        </FormField>

                        <!-- Source -->
                        <FormField
                            class="col-md-4"
                            label="Lead source"
                            :error="errors.source"
                        >
                            <select
                                v-model="form.source"
                                class="form-select"
                                :class="{
                                    'is-invalid':
                                        errors.source
                                }"
                            >
                                <option value="">
                                    Select source
                                </option>

                                <option
                                    v-for="source in CONTACT_SOURCES"
                                    :key="source.value"
                                    :value="source.value"
                                >
                                    {{ source.label }}
                                </option>
                            </select>
                        </FormField>

                        <!-- Owner -->
                        <FormField
                            class="col-md-6"
                            label="Contact owner"
                            :error="errors.owner_id"
                        >
                            <!--
                                Replace this with your usersApi.list()
                                once you implement user ownership.
                            -->
                            <select
                                v-model="form.owner_id"
                                class="form-select"
                                :class="{
                                    'is-invalid':
                                        errors.owner_id
                                }"
                            >
                                <option :value="null">
                                    Unassigned
                                </option>
                            </select>

                            <div class="form-text">
                                The CRM user responsible for this contact.
                            </div>
                        </FormField>

                    </div>

                </div>
            </div>

            <!-- ===================================================== -->
            <!-- NOTES -->
            <!-- ===================================================== -->

            <div class="card border-0 shadow-sm mb-4">

                <div class="card-header bg-white py-3">

                    <div class="d-flex align-items-center gap-3">

                        <div class="section-icon bg-secondary-subtle text-secondary">
                            <i class="bi bi-sticky"></i>
                        </div>

                        <div>
                            <h5 class="mb-0 fw-semibold">
                                Notes
                            </h5>

                            <small class="text-muted">
                                Internal notes about this contact
                            </small>
                        </div>

                    </div>

                </div>

                <div class="card-body">

                    <FormField
                        label="Notes"
                        :error="errors.notes"
                    >
                        <textarea
                            v-model="form.notes"
                            rows="5"
                            class="form-control"
                            placeholder="Add useful information about this contact..."
                            :class="{
                                'is-invalid':
                                    errors.notes
                            }"
                        ></textarea>

                        <div class="form-text">
                            These notes are visible to authorized CRM users.
                        </div>
                    </FormField>

                </div>
            </div>

            <!-- ===================================================== -->
            <!-- ACTION BAR -->
            <!-- ===================================================== -->

            <div
                class="card border-0 shadow-sm mb-5 sticky-action-bar"
            >
                <div
                    class="card-body d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3"
                >

                    <div class="small text-muted">
                        <i class="bi bi-shield-check me-1"></i>

                        All required fields are marked with
                        <span class="text-danger">*</span>
                    </div>

                    <div class="d-flex gap-2">

                        <button
                            type="button"
                            class="btn btn-light border"
                            :disabled="saving"
                            @click="cancel"
                        >
                            Cancel
                        </button>

                        <button
                            type="submit"
                            class="btn btn-primary px-4"
                            :disabled="saving"
                        >
                            <span
                                v-if="saving"
                                class="spinner-border spinner-border-sm me-2"
                                aria-hidden="true"
                            ></span>

                            <i
                                v-else
                                class="bi bi-check-lg me-1"
                            ></i>

                            {{
                                saving
                                    ? 'Saving...'
                                    : isEdit
                                        ? 'Save changes'
                                        : 'Create contact'
                            }}
                        </button>

                    </div>

                </div>
            </div>

        </form>

    </div>
</template>

<style scoped>
.contact-form-page {
    max-width: 1200px;
    margin: 0 auto;
}

/*
|--------------------------------------------------------------------------
| Section icon
|--------------------------------------------------------------------------
*/

.section-icon {
    width: 42px;
    height: 42px;

    display: flex;
    align-items: center;
    justify-content: center;

    border-radius: 12px;

    font-size: 19px;
}

/*
|--------------------------------------------------------------------------
| Input icons
|--------------------------------------------------------------------------
*/

.input-icon-wrapper {
    position: relative;
}

.input-icon-wrapper > i {
    position: absolute;

    left: 14px;
    top: 50%;

    transform: translateY(-50%);

    color: #6c757d;

    pointer-events: none;

    z-index: 2;
}

.input-icon-wrapper .form-control {
    padding-left: 40px;
}

/*
|--------------------------------------------------------------------------
| Cards
|--------------------------------------------------------------------------
*/

.card {
    border-radius: 12px;
}

.card-header {
    border-bottom: 1px solid #edf0f2;
}

/*
|--------------------------------------------------------------------------
| Sticky action bar
|--------------------------------------------------------------------------
*/

.sticky-action-bar {
    position: sticky;
    bottom: 16px;
    z-index: 20;
}

/*
|--------------------------------------------------------------------------
| Form controls
|--------------------------------------------------------------------------
*/

.form-control,
.form-select {
    min-height: 42px;
}

textarea.form-control {
    min-height: auto;
}

/*
|--------------------------------------------------------------------------
| Mobile
|--------------------------------------------------------------------------
*/

@media (max-width: 767.98px) {
    .contact-form-page {
        max-width: 100%;
    }

    .sticky-action-bar {
        position: static;
    }
}
</style>
