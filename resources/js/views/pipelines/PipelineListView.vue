<script setup>
import { onMounted, ref } from 'vue';
import { pipelinesApi } from '@/api/pipelines';
import { useToastStore } from '@/stores/toast';
import { parseApiError } from '@/utils/errors';
import ConfirmModal from '@/components/ConfirmModal.vue';
import LoadingBlock from '@/components/LoadingBlock.vue';

const toast=useToastStore(); const pipelines=ref([]); const loading=ref(true); const deleting=ref(false); const toDelete=ref(null);
async function load(){ loading.value=true; try { const r=await pipelinesApi.list({per_page:100}); pipelines.value=r.data; } catch(e){ toast.error(parseApiError(e).message); } finally{ loading.value=false; } }
async function remove(){ deleting.value=true; try{ await pipelinesApi.remove(toDelete.value.id); toast.success('Pipeline deleted.'); toDelete.value=null; await load(); } catch(e){ toast.error(parseApiError(e).message); } finally{ deleting.value=false; } }
onMounted(load);
</script>
<template>
<div class="d-flex justify-content-between align-items-center mb-4"><div><h1 class="h3 mb-1">Pipelines</h1><div class="text-muted small">Configure sales processes and manage deal stages.</div></div><RouterLink :to="{name:'pipelines.create'}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>New pipeline</RouterLink></div>
<LoadingBlock v-if="loading" />
<div v-else class="row g-3">
 <div v-for="p in pipelines" :key="p.id" class="col-xl-6">
  <div class="card h-100 shadow-sm"><div class="card-body">
   <div class="d-flex justify-content-between"><div><h5 class="mb-1">{{p.name}} <span v-if="p.is_default" class="badge text-bg-primary ms-1">Default</span></h5><div class="text-muted small">{{p.description || 'No description'}}</div></div><span class="badge" :class="p.is_active?'text-bg-success':'text-bg-secondary'">{{p.is_active?'Active':'Inactive'}}</span></div>
   <div class="d-flex flex-wrap gap-2 mt-3"><span v-for="s in p.stages" :key="s.id" class="badge" :class="`text-bg-${s.color}`">{{s.name}} <small>({{s.deals_count ?? 0}})</small></span></div>
   <div class="d-flex justify-content-between align-items-center mt-4"><span class="small text-muted">{{p.deals_count ?? 0}} deals</span><div><RouterLink :to="{name:'pipelines.board',params:{id:p.id}}" class="btn btn-sm btn-outline-secondary">Board</RouterLink><RouterLink :to="{name:'pipelines.edit',params:{id:p.id}}" class="btn btn-sm btn-outline-primary ms-1">Edit</RouterLink><button v-if="!p.is_default" class="btn btn-sm btn-outline-danger ms-1" @click="toDelete=p">Delete</button></div></div>
  </div></div>
 </div>
 <div v-if="!pipelines.length" class="col-12"><div class="card"><div class="card-body text-center text-muted py-5">No pipelines found.</div></div></div>
</div>
<ConfirmModal :show="!!toDelete" title="Delete pipeline" :message="`Delete “${toDelete?.name}”?`" :loading="deleting" @confirm="remove" @cancel="toDelete=null" />
</template>
