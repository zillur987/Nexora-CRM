<script setup>
import { computed, onMounted, ref } from 'vue';
import { dashboardApi } from '@/api/dashboard';
import { dealStageMeta } from '@/constants';
import { money } from '@/utils/format';
import LoadingBlock from '@/components/LoadingBlock.vue';
import StatusBadge from '@/components/StatusBadge.vue';

const stats = ref(null);
const error = ref('');

const maxCount = computed(() => Math.max(1, ...(stats.value?.pipeline ?? []).map((r) => r.count)));

onMounted(async () => {
    try {
        stats.value = await dashboardApi.get();
    } catch {
        error.value = 'Failed to load the dashboard.';
    }
});
</script>

<template>
    <h1 class="h3 mb-4">Dashboard</h1>

    <div v-if="error" class="alert alert-danger">{{ error }}</div>
    <LoadingBlock v-else-if="!stats" />

    <template v-else>
        <div class="row g-3 mb-4">
            <div class="col-md-3"><div class="card"><div class="card-body">
                <div class="text-muted small">Total contacts</div>
                <div class="fs-2 fw-semibold">{{ stats.contacts }}</div>
            </div></div></div>
            <div class="col-md-3"><div class="card"><div class="card-body">
                <div class="text-muted small">Leads</div>
                <div class="fs-2 fw-semibold">{{ stats.leads }}</div>
            </div></div></div>
            <div class="col-md-3"><div class="card"><div class="card-body">
                <div class="text-muted small">Open deals</div>
                <div class="fs-2 fw-semibold">{{ stats.open_deals }}</div>
            </div></div></div>
            <div class="col-md-3"><div class="card"><div class="card-body">
                <div class="text-muted small">Won revenue</div>
                <template v-if="Object.keys(stats.won_revenue).length">
                    <div v-for="(total, currency) in stats.won_revenue" :key="currency" class="fs-4 fw-semibold">
                        {{ total }} <small class="text-muted">{{ currency }}</small>
                    </div>
                </template>
                <div v-else class="fs-4 fw-semibold">0.00</div>
            </div></div></div>
        </div>

        <div class="row g-3">
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header bg-white fw-semibold">Pipeline</div>
                    <div class="card-body">
                        <p v-if="!stats.pipeline.length" class="text-muted mb-0">No deals yet.</p>
                        <div v-for="row in stats.pipeline" :key="row.stage + row.currency" class="mb-3">
                            <div class="d-flex justify-content-between small mb-1">
                                <span><StatusBadge kind="deal" :value="row.stage" /> {{ row.count }} deals</span>
                                <span class="text-muted">{{ money(row.total_amount, row.currency) }}</span>
                            </div>
                            <div class="progress" style="height: 8px">
                                <div
                                    class="progress-bar"
                                    :class="`bg-${dealStageMeta[row.stage]?.color ?? 'secondary'}`"
                                    :style="{ width: Math.round((row.count / maxCount) * 100) + '%' }"
                                ></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card mb-3">
                    <div class="card-header bg-white d-flex justify-content-between">
                        <span class="fw-semibold">Recent deals</span>
                        <RouterLink :to="{ name: 'deals.index' }" class="small">View all</RouterLink>
                    </div>
                    <ul class="list-group list-group-flush">
                        <li v-if="!stats.recent_deals.length" class="list-group-item text-muted">No deals yet.</li>
                        <li v-for="d in stats.recent_deals" :key="d.id" class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <RouterLink :to="{ name: 'deals.show', params: { id: d.id } }" class="fw-medium text-decoration-none">{{ d.title }}</RouterLink>
                                <div class="small text-muted">{{ d.contact?.full_name }}</div>
                            </div>
                            <StatusBadge kind="deal" :value="d.stage" />
                        </li>
                    </ul>
                </div>

                <div class="card">
                    <div class="card-header bg-white d-flex justify-content-between">
                        <span class="fw-semibold">Recent contacts</span>
                        <RouterLink :to="{ name: 'contacts.index' }" class="small">View all</RouterLink>
                    </div>
                    <ul class="list-group list-group-flush">
                        <li v-if="!stats.recent_contacts.length" class="list-group-item text-muted">No contacts yet.</li>
                        <li v-for="c in stats.recent_contacts" :key="c.id" class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <RouterLink :to="{ name: 'contacts.show', params: { id: c.id } }" class="fw-medium text-decoration-none">{{ c.full_name }}</RouterLink>
                                <div class="small text-muted">{{ c.email }}</div>
                            </div>
                            <StatusBadge kind="contact" :value="c.status" />
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </template>
</template>
