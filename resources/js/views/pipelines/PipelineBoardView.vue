<script setup>
import { onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { pipelinesApi } from '@/api/pipelines';
import { dealsApi } from '@/api/deals';
import { useToastStore } from '@/stores/toast';
import { parseApiError } from '@/utils/errors';
import { money } from '@/utils/format';
import LoadingBlock from '@/components/LoadingBlock.vue';
const props=defineProps({id:{type:String,required:true}});const router=useRouter();const toast=useToastStore();const pipeline=ref(null);const stages=ref([]);const loading=ref(true);const moving=ref(null);const dragged=ref(null);
async function load(){loading.value=true;try{pipeline.value=await pipelinesApi.get(props.id);stages.value=await pipelinesApi.board(props.id);}catch(e){toast.error(parseApiError(e).message);router.replace({name:'pipelines.index'});}finally{loading.value=false;}}
function dragStart(stage,deal){dragged.value={dealId:deal.id,fromStageId:stage.id};}
async function drop(target){if(!dragged.value||dragged.value.fromStageId===target.id)return;const item=dragged.value;moving.value=item.dealId;try{await dealsApi.movePipelineStage(item.dealId,target.id);toast.success(`Deal moved to ${target.name}.`);await load();}catch(e){toast.error(parseApiError(e).message);}finally{moving.value=null;dragged.value=null;}}
onMounted(load);
</script>
<template><div class="d-flex justify-content-between align-items-center mb-4"><div><h1 class="h3 mb-1">{{pipeline?.name||'Pipeline board'}}</h1><div class="text-muted small">Drag open deals between stages.</div></div><div><RouterLink :to="{name:'pipelines.edit',params:{id:props.id}}" class="btn btn-outline-primary me-2">Manage stages</RouterLink><RouterLink :to="{name:'pipelines.index'}" class="btn btn-outline-secondary">Back</RouterLink></div></div><LoadingBlock v-if="loading"/><div v-else class="d-flex gap-3 overflow-auto pb-3" style="min-height:70vh"><div v-for="stage in stages" :key="stage.id" class="card flex-shrink-0" style="width:320px" @dragover.prevent @drop.prevent="drop(stage)"><div class="card-header bg-white d-flex justify-content-between align-items-center"><div><strong>{{stage.name}}</strong><div class="small text-muted">{{stage.probability}}% probability</div></div><span class="badge" :class="`text-bg-${stage.color}`">{{stage.deals_count}}</span></div><div class="card-body p-2 bg-light"><div v-for="deal in stage.deals" :key="deal.id" class="card mb-2 shadow-sm" :class="{'opacity-50':moving===deal.id}" draggable="true" @dragstart="dragStart(stage,deal)"><div class="card-body p-3"><RouterLink :to="{name:'deals.show',params:{id:deal.id}}" class="fw-semibold text-decoration-none">{{deal.title}}</RouterLink><div class="small text-muted mt-2">{{deal.contact?.name||'No contact'}}</div><div class="fw-semibold mt-2">{{money(deal.amount,deal.currency)}}</div></div></div><div v-if="!stage.deals.length" class="text-center text-muted small py-5">Drop deals here</div></div></div></div></template>
