<script setup>
import { computed, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import { dealsApi } from '@/api/deals';
import { DEAL_FLOW, dealStageMeta } from '@/constants';
import { useToastStore } from '@/stores/toast';
import { parseApiError } from '@/utils/errors';
import { formatDate, money } from '@/utils/format';
import ConfirmModal from '@/components/ConfirmModal.vue';
import LoadingBlock from '@/components/LoadingBlock.vue';
import StatusBadge from '@/components/StatusBadge.vue';

const props = defineProps({ id: { type: String, required: true } });

const router = useRouter();
const toast = useToastStore();

const deal = ref(null);
const movingTo = ref(null);
const confirming = ref(false);
const deleting = ref(false);

const currentIndex = computed(() => DEAL_FLOW.indexOf(deal.value?.stage));

watch(
    () => props.id,
    async (id) => {
        deal.value = null;
        try {
            deal.value = await dealsApi.get(id);
        } catch {
            toast.error('Deal not found.');
            router.replace({ name: 'deals.index' });
        }
    },
    { immediate: true },
);

async function moveTo(stage) {
    movingTo.value = stage;
    try {
        deal.value = await dealsApi.changeStage(props.id, stage);
        toast.success(`Deal moved to ${dealStageMeta[stage].label}.`);
    } catch (e) {
        toast.error(parseApiError(e).message);
    } finally {
        movingTo.value = null;
    }
}

async function remove() {
    deleting.value = true;
    try {
        await dealsApi.remove(props.id);
        toast.success('Deal deleted.');
        router.push({ name: 'deals.index' });
    } catch (e) {
        toast.error(parseApiError(e).message);
        confirming.value = false;
    } finally {
        deleting.value = false;
    }
}
</script>

<template>
    <LoadingBlock v-if="!deal" />

    <template v-else>
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 mb-0">{{ deal.title }}</h1>
            <div class="d-flex gap-2">
                <RouterLink v-if="!deal.is_closed" :to="{ name: 'deals.edit', params: { id } }" class="btn btn-outline-primary">
                    <i class="bi bi-pencil me-1"></i>Edit
                </RouterLink>
                <button v-if="deal.stage !== 'won'" class="btn btn-outline-danger" @click="confirming = true">
                    <i class="bi bi-trash me-1"></i>Delete
                </button>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-body">
                <div class="d-flex flex-wrap gap-2 mb-3">
                    <span
                        v-for="(step, i) in DEAL_FLOW"
                        :key="step"
                        class="badge px-3 py-2"
                        :class="i <= currentIndex ? `text-bg-${dealStageMeta[step].color}` : 'text-bg-light border text-muted'"
                    >{{ dealStageMeta[step].label }}</span>
                    <span v-if="deal.stage === 'lost'" class="badge text-bg-danger px-3 py-2">Lost</span>
                </div>

                <p v-if="deal.is_closed" class="text-muted mb-0">
                    <i class="bi bi-lock me-1"></i>This deal is {{ deal.stage }} and can no longer change stage.
                </p>
                <div v-else class="d-flex flex-wrap gap-2 align-items-center">
                    <span class="text-muted small me-2">Move to:</span>
                    <button
                        v-for="next in deal.allowed_transitions"
                        :key="next"
                        class="btn btn-sm"
                        :class="`btn-outline-${dealStageMeta[next].color}`"
                        :disabled="movingTo !== null"
                        @click="moveTo(next)"
                    >
                        <span v-if="movingTo === next" class="spinner-border spinner-border-sm me-1"></span>
                        {{ dealStageMeta[next].label }}
                    </button>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-3 text-muted">Contact</dt>
                    <dd class="col-sm-9">
                        <RouterLink :to="{ name: 'contacts.show', params: { id: deal.contact_id } }" class="text-decoration-none">{{ deal.contact?.full_name }}</RouterLink>
                    </dd>
                    <dt class="col-sm-3 text-muted">Amount</dt>
                    <dd class="col-sm-9">{{ money(deal.amount, deal.currency) }}</dd>
                    <dt class="col-sm-3 text-muted">Stage</dt>
                    <dd class="col-sm-9"><StatusBadge kind="deal" :value="deal.stage" /></dd>
                    <dt class="col-sm-3 text-muted">Expected close</dt>
                    <dd class="col-sm-9">{{ formatDate(deal.expected_close_date) }}</dd>
                    <dt class="col-sm-3 text-muted">Closed at</dt>
                    <dd class="col-sm-9">{{ formatDate(deal.closed_at, true) }}</dd>
                    <dt class="col-sm-3 text-muted">Created</dt>
                    <dd class="col-sm-9 mb-0">{{ formatDate(deal.created_at, true) }}</dd>
                </dl>
            </div>
        </div>

        <ConfirmModal
            :show="confirming"
            title="Delete deal"
            :message="`Delete “${deal.title}”?`"
            :loading="deleting"
            @confirm="remove"
            @cancel="confirming = false"
        />
    </template>
</template>
