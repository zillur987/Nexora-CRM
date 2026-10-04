<script setup>
import { ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import { leadsApi } from '@/api/leads';
import { leadSourceMeta, leadStatusMeta } from '@/constants';
import { useToastStore } from '@/stores/toast';
import { parseApiError } from '@/utils/errors';
import { formatDate, money } from '@/utils/format';
import ConfirmModal from '@/components/ConfirmModal.vue';
import ConvertLeadModal from '@/components/ConvertLeadModal.vue';
import LoadingBlock from '@/components/LoadingBlock.vue';
import ScoreBar from '@/components/ScoreBar.vue';
import StatusBadge from '@/components/StatusBadge.vue';

const props = defineProps({ id: { type: String, required: true } });

const router = useRouter();
const toast = useToastStore();

const lead = ref(null);
const busy = ref(false);
const confirmingDelete = ref(false);
const converting = ref(false);
const convertErrors = ref({});

watch(
    () => props.id,
    async (id) => {
        lead.value = null;
        try {
            lead.value = await leadsApi.get(id);
        } catch {
            toast.error('Lead not found.');
            router.replace({ name: 'leads.index' });
        }
    },
    { immediate: true },
);

/** Runs a mutation with a shared busy flag and uniform error toast. */
async function run(action) {
    busy.value = true;
    try {
        await action();
    } catch (e) {
        toast.error(parseApiError(e).message);
        return false;
    } finally {
        busy.value = false;
    }
    return true;
}

const moveTo = (status) =>
    run(async () => {
        lead.value = await leadsApi.changeStatus(props.id, status);
        toast.success(`Lead marked ${leadStatusMeta[status].label.toLowerCase()}.`);
    });

async function convert(payload) {
    convertErrors.value = {};
    busy.value = true;
    try {
        const result = await leadsApi.convert(props.id, payload);
        lead.value = result.lead;
        converting.value = false;
        toast.success(result.contact_created ? 'Lead converted to a new contact.' : 'Lead linked to an existing contact.');
        router.push({ name: 'contacts.show', params: { id: result.contact.id } });
    } catch (e) {
        const parsed = parseApiError(e);
        convertErrors.value = parsed.fields;
        toast.error(parsed.message);
    } finally {
        busy.value = false;
    }
}

async function remove() {
    const ok = await run(async () => {
        await leadsApi.remove(props.id);
        toast.success('Lead deleted.');
    });
    if (ok) router.push({ name: 'leads.index' });
    else confirmingDelete.value = false;
}
</script>

<template>
    <LoadingBlock v-if="!lead" />

    <template v-else>
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <div class="d-flex align-items-center gap-3">
                <h1 class="h3 mb-0">{{ lead.full_name }}</h1>
                <StatusBadge kind="lead" :value="lead.status" />
            </div>
            <div v-if="!lead.is_converted" class="d-flex gap-2">
                <RouterLink :to="{ name: 'leads.edit', params: { id } }" class="btn btn-outline-primary"><i class="bi bi-pencil me-1"></i>Edit</RouterLink>
                <button class="btn btn-outline-danger" :disabled="busy" @click="confirmingDelete = true"><i class="bi bi-trash me-1"></i>Delete</button>
            </div>
            <RouterLink v-else-if="lead.converted_contact_id" :to="{ name: 'contacts.show', params: { id: lead.converted_contact_id } }" class="btn btn-outline-success">
                <i class="bi bi-person-check me-1"></i>View contact
            </RouterLink>
        </div>

        <div v-if="!lead.is_converted" class="card mb-3">
            <div class="card-body d-flex flex-wrap align-items-center gap-2">
                <span class="text-muted small me-2">Move to:</span>
                <button
                    v-for="s in lead.allowed_transitions"
                    :key="s"
                    class="btn btn-sm"
                    :class="`btn-outline-${leadStatusMeta[s].color}`"
                    :disabled="busy"
                    @click="moveTo(s)"
                >
                    {{ leadStatusMeta[s].label }}
                </button>
                <button v-if="lead.can_convert" class="btn btn-success btn-sm ms-auto" :disabled="busy" @click="converting = true">
                    <i class="bi bi-arrow-right-circle me-1"></i>Convert
                </button>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-lg-5">
                <div class="card"><div class="card-body">
                    <dl class="mb-0">
                        <dt class="small text-muted">Email</dt><dd>{{ lead.email }}</dd>
                        <dt class="small text-muted">Phone</dt><dd>{{ lead.phone ?? '—' }}</dd>
                        <dt class="small text-muted">Company</dt><dd>{{ lead.company ?? '—' }}</dd>
                        <dt class="small text-muted">Job title</dt><dd>{{ lead.job_title ?? '—' }}</dd>
                        <dt class="small text-muted">Owner</dt><dd class="mb-0">{{ lead.owner?.name ?? 'Unassigned' }}</dd>
                    </dl>
                </div></div>
            </div>

            <div class="col-lg-7">
                <div class="card mb-3"><div class="card-body">
                    <div class="row">
                        <div class="col-sm-4">
                            <div class="small text-muted">Source</div>
                            <div>{{ leadSourceMeta[lead.source]?.label ?? lead.source }}</div>
                        </div>
                        <div class="col-sm-4">
                            <div class="small text-muted">Estimated value</div>
                            <div>{{ lead.estimated_value ? money(lead.estimated_value, lead.currency) : '—' }}</div>
                        </div>
                        <div class="col-sm-4">
                            <div class="small text-muted mb-1">Score</div>
                            <ScoreBar :value="lead.score" />
                        </div>
                    </div>
                    <hr />
                    <div class="row small text-muted">
                        <div class="col-sm-4">Created<br /><span class="text-body">{{ formatDate(lead.created_at, true) }}</span></div>
                        <div class="col-sm-4">Last contacted<br /><span class="text-body">{{ formatDate(lead.last_contacted_at, true) }}</span></div>
                        <div v-if="lead.converted_at" class="col-sm-4">Converted<br /><span class="text-body">{{ formatDate(lead.converted_at, true) }}</span></div>
                    </div>
                </div></div>

                <div class="card">
                    <div class="card-header bg-white fw-semibold">Notes</div>
                    <div class="card-body" style="white-space: pre-wrap">{{ lead.notes || 'No notes.' }}</div>
                </div>
            </div>
        </div>

        <ConvertLeadModal :show="converting" :lead="lead" :loading="busy" :errors="convertErrors" @confirm="convert" @cancel="converting = false" />

        <ConfirmModal
            :show="confirmingDelete"
            title="Delete lead"
            :message="`Delete ${lead.full_name}?`"
            :loading="busy"
            @confirm="remove"
            @cancel="confirmingDelete = false"
        />
    </template>
</template>
