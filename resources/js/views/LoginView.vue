<script setup>
import { reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/auth';
import { parseApiError } from '@/utils/errors';
import FormField from '@/components/FormField.vue';

const auth = useAuthStore();
const route = useRoute();
const router = useRouter();

const form = reactive({ email: '', password: '' });
const errors = ref({});
const message = ref('');
const loading = ref(false);

async function submit() {
    loading.value = true;
    errors.value = {};
    message.value = '';
    try {
        await auth.login({ ...form });
        router.push(route.query.redirect || { name: 'dashboard' });
    } catch (e) {
        const parsed = parseApiError(e);
        errors.value = parsed.fields;
        if (!Object.keys(parsed.fields).length) message.value = parsed.message;
    } finally {
        loading.value = false;
    }
}
</script>

<template>
    <div class="d-flex align-items-center" style="min-height: 100vh">
        <div class="container" style="max-width: 420px">
            <div class="text-center mb-4">
                <i class="bi bi-people-fill text-primary" style="font-size: 2.5rem"></i>
                <h1 class="h3 mt-2">CRM Admin</h1>
            </div>

            <div class="card">
                <div class="card-body p-4">
                    <div v-if="message" class="alert alert-danger py-2">{{ message }}</div>

                    <form class="d-grid gap-3" @submit.prevent="submit">
                        <FormField label="Email" :error="errors.email">
                            <input v-model="form.email" type="email" required autofocus class="form-control" :class="{ 'is-invalid': errors.email }" />
                        </FormField>
                        <FormField label="Password" :error="errors.password">
                            <input v-model="form.password" type="password" required class="form-control" :class="{ 'is-invalid': errors.password }" />
                        </FormField>
                        <button class="btn btn-primary" :disabled="loading">
                            <span v-if="loading" class="spinner-border spinner-border-sm me-1"></span>Sign in
                        </button>
                    </form>
                </div>
            </div>
            <p class="text-center text-muted small mt-3">Demo: admin@example.com / password</p>
        </div>
    </div>
</template>
