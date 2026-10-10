<template>
  <section class="pipeline-page">
    <header class="page-header">
      <div>
        <h1>Sales pipelines</h1>
        <p>Configure sales processes and the stages your deals move through.</p>
      </div>
      <button class="btn-primary" type="button" @click="openCreate">+ New pipeline</button>
    </header>

    <div class="toolbar">
      <input v-model="search" type="search" placeholder="Search pipelines…" @input="debouncedLoad" />
      <select v-model="activeFilter" @change="load">
        <option value="">All statuses</option><option value="1">Active</option><option value="0">Inactive</option>
      </select>
      <button class="btn-secondary" type="button" @click="load">Refresh</button>
    </div>

    <p v-if="error" class="alert-error">{{ error }}</p>
    <div v-if="loading" class="empty-state">Loading pipelines…</div>
    <div v-else-if="!pipelines.length" class="empty-state">No pipelines found. Create one to define your sales process.</div>

    <article v-for="pipeline in pipelines" :key="pipeline.id" class="pipeline-card">
      <div class="pipeline-top">
        <div class="pipeline-title">
          <h2>{{ pipeline.name }} <span v-if="pipeline.is_default" class="pill default">Default</span>
            <span v-if="!pipeline.is_active" class="pill inactive">Inactive</span></h2>
          <p>{{ pipeline.description || 'No description provided.' }}</p>
          <div class="meta">{{ pipeline.stages_count }} stages <span>·</span> {{ pipeline.deals_count }} deals</div>
        </div>
        <div class="actions">
          <button class="btn-secondary" @click="editPipeline(pipeline)">Edit</button>
          <button class="btn-secondary" @click="toggleStages(pipeline)">{{ expanded[pipeline.id] ? 'Hide stages' : 'Manage stages' }}</button>
          <button class="btn-danger" :disabled="pipeline.deals_count > 0 || pipeline.is_default" @click="deletePipeline(pipeline)">Delete</button>
        </div>
      </div>

      <div v-if="expanded[pipeline.id]" class="stage-panel">
        <div class="stage-heading"><h3>Stages</h3><button class="btn-primary" @click="openStage(pipeline)">+ Add stage</button></div>
        <div v-if="!pipeline.stages?.length" class="empty-inline">No stages configured.</div>
        <div v-for="stage in pipeline.stages" :key="stage.id" class="stage-row">
          <div class="stage-name"><strong>{{ stage.name }}</strong><span class="pill" :class="stage.outcome">{{ stage.outcome }}</span>
            <small>{{ stage.probability }}% probability · Order {{ stage.sort_order }}</small></div>
          <div class="actions"><button class="btn-secondary" @click="openStage(pipeline, stage)">Edit</button>
            <button class="btn-danger" @click="deleteStage(pipeline, stage)">Delete</button></div>
        </div>
      </div>
    </article>

    <div v-if="showPipelineForm" class="drawer-backdrop" @click.self="showPipelineForm = false">
      <form class="drawer-panel" @submit.prevent="savePipeline">
        <div class="drawer-header"><h2>{{ editing ? 'Edit pipeline' : 'New pipeline' }}</h2><button type="button" class="close" @click="showPipelineForm = false">×</button></div>
        <label>Pipeline name <input v-model.trim="form.name" required maxlength="120" /></label>
        <label>Description <textarea v-model="form.description" rows="3" maxlength="3000" /></label>
        <label class="check"><input v-model="form.is_default" type="checkbox" /> Set as default pipeline</label>
        <label class="check"><input v-model="form.is_active" type="checkbox" /> Active</label>
        <label>Display order <input v-model.number="form.sort_order" type="number" min="0" max="65535" /></label>
        <p v-if="formError" class="alert-error">{{ formError }}</p>
        <div class="drawer-footer"><button type="button" class="btn-secondary" @click="showPipelineForm = false">Cancel</button><button class="btn-primary" :disabled="saving">{{ saving ? 'Saving…' : 'Save pipeline' }}</button></div>
      </form>
    </div>

    <div v-if="showStageForm" class="drawer-backdrop" @click.self="showStageForm = false">
      <form class="drawer-panel" @submit.prevent="saveStage">
        <div class="drawer-header"><h2>{{ editingStage ? 'Edit stage' : 'New stage' }}</h2><button type="button" class="close" @click="showStageForm = false">×</button></div>
        <p class="drawer-subheading">Pipeline: {{ selectedPipeline?.name }}</p>
        <label>Stage name <input v-model.trim="stageForm.name" required maxlength="120" /></label>
        <label>Outcome <select v-model="stageForm.outcome"><option value="open">Open</option><option value="won">Won</option><option value="lost">Lost</option></select></label>
        <label>Win probability (%) <input v-model.number="stageForm.probability" type="number" min="0" max="100" required /></label>
        <label>Display order <input v-model.number="stageForm.sort_order" type="number" min="0" max="65535" /></label>
        <label class="check"><input v-model="stageForm.is_active" type="checkbox" /> Active</label>
        <p v-if="stageError" class="alert-error">{{ stageError }}</p>
        <div class="drawer-footer"><button type="button" class="btn-secondary" @click="showStageForm = false">Cancel</button><button class="btn-primary" :disabled="saving">{{ saving ? 'Saving…' : 'Save stage' }}</button></div>
      </form>
    </div>
  </section>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue';
import { pipelinesApi } from '@/api/pipelines';

const pipelines = ref([]);
const loading = ref(false);
const saving = ref(false);
const error = ref('');
const formError = ref('');
const stageError = ref('');
const search = ref('');
const activeFilter = ref('');
const expanded = reactive({});
const showPipelineForm = ref(false);
const showStageForm = ref(false);
const editing = ref(null);
const editingStage = ref(null);
const selectedPipeline = ref(null);
const form = reactive({ name: '', description: '', is_default: false, is_active: true, sort_order: 0 });
const stageForm = reactive({ name: '', outcome: 'open', probability: 0, sort_order: 10, is_active: true });
let searchTimer;

async function load() {
  loading.value = true; error.value = '';
  try {
    const response = await pipelinesApi.list({ search: search.value || undefined, is_active: activeFilter.value, per_page: 100 });
    pipelines.value = response.data || [];
  } catch (e) { error.value = message(e); }
  finally { loading.value = false; }
}
function debouncedLoad() { clearTimeout(searchTimer); searchTimer = setTimeout(load, 250); }
function message(e) { return e?.response?.data?.message || Object.values(e?.response?.data?.errors || {}).flat().join(' ') || 'Something went wrong. Please try again.'; }
function openCreate() {
  editing.value = null; formError.value = '';
  Object.assign(form, { name: '', description: '', is_default: false, is_active: true, sort_order: pipelines.value.length * 10 });
  showPipelineForm.value = true;
}
function editPipeline(p) {
  editing.value = p; formError.value = '';
  Object.assign(form, { name: p.name, description: p.description || '', is_default: p.is_default, is_active: p.is_active, sort_order: p.sort_order });
  showPipelineForm.value = true;
}
async function savePipeline() {
  saving.value = true; formError.value = '';
  try {
    const payload = { ...form };
    if (editing.value) await pipelinesApi.update(editing.value.id, payload); else await pipelinesApi.create(payload);
    showPipelineForm.value = false; await load();
  } catch (e) { formError.value = message(e); }
  finally { saving.value = false; }
}
async function toggleStages(p) {
  if (expanded[p.id]) { expanded[p.id] = false; return; }
  try {
    const full = await pipelinesApi.get(p.id);
    Object.assign(p, full);
    expanded[p.id] = true;
  } catch (e) { error.value = message(e); }
}
function openStage(p, stage = null) {
  selectedPipeline.value = p; editingStage.value = stage; stageError.value = '';
  Object.assign(stageForm, stage ? { name: stage.name, outcome: stage.outcome, probability: stage.probability, sort_order: stage.sort_order, is_active: stage.is_active } : { name: '', outcome: 'open', probability: 0, sort_order: (p.stages?.length || 0) * 10 + 10, is_active: true });
  showStageForm.value = true;
}
async function saveStage() {
  saving.value = true; stageError.value = '';
  try {
    const payload = { ...stageForm };
    if (editingStage.value) await pipelinesApi.updateStage(selectedPipeline.value.id, editingStage.value.id, payload);
    else await pipelinesApi.createStage(selectedPipeline.value.id, payload);
    showStageForm.value = false;
    const full = await pipelinesApi.get(selectedPipeline.value.id); Object.assign(selectedPipeline.value, full);
    await load();
  } catch (e) { stageError.value = message(e); }
  finally { saving.value = false; }
}
async function deleteStage(p, stage) {
  if (!confirm(`Delete stage "${stage.name}"?`)) return;
  try { await pipelinesApi.removeStage(p.id, stage.id); const full = await pipelinesApi.get(p.id); Object.assign(p, full); await load(); }
  catch (e) { error.value = message(e); }
}
async function deletePipeline(p) {
  if (!confirm(`Delete pipeline "${p.name}"?`)) return;
  try { await pipelinesApi.remove(p.id); await load(); }
  catch (e) { error.value = message(e); }
}
onMounted(load);
</script>

<style scoped>
.pipeline-page {
  font-family: var(--crm-font);
  font-size: var(--crm-fs);
  line-height: 1.5;
  color: var(--crm-text);
  padding: 1.25rem;
}
.page-header, .pipeline-top, .stage-heading, .drawer-header, .drawer-footer, .toolbar, .actions {
  display: flex; align-items: center; justify-content: space-between; gap: .75rem;
}
.page-header { margin-bottom: 1.25rem; }
h1, h2, h3, p { margin: 0; }
h1 { font-size: 1.5rem; font-weight: 600; letter-spacing: -.02em; color: var(--crm-text-strong); }
.page-header p, .pipeline-title p, .drawer-subheading { color: var(--crm-text-muted); margin-top: .3rem; }
.toolbar { margin-bottom: 1rem; justify-content: flex-start; }
.pipeline-page input, .pipeline-page select, .pipeline-page textarea {
  font-family: var(--crm-font); font-size: var(--crm-fs); line-height: 1.5;
  color: var(--crm-text); background: var(--crm-surface); border: 1px solid var(--crm-border);
  border-radius: var(--crm-radius); padding: .375rem .75rem; min-height: var(--crm-control-h); width: 100%;
}
.pipeline-page input[type="checkbox"] { width: 1rem; min-height: 1rem; padding: 0; }
.toolbar input { max-width: 22rem; } .toolbar select { max-width: 12rem; }
.pipeline-page button { font-family: var(--crm-font); font-size: var(--crm-fs); font-weight: 500; cursor: pointer; border-radius: var(--crm-radius); padding: .4rem .875rem; min-height: var(--crm-control-h); border: 1px solid var(--crm-border); }
.pipeline-page button:disabled { opacity: .45; cursor: not-allowed; }
.btn-primary { background: var(--crm-primary); color: #fff; border-color: var(--crm-primary) !important; }
.btn-secondary { background: var(--crm-surface); color: var(--crm-text-strong); }
.btn-danger { background: var(--crm-surface); color: var(--crm-danger, #b42318); border-color: var(--crm-border); }
.pipeline-card { background: var(--crm-surface); border: 1px solid var(--crm-border); border-radius: var(--crm-radius); margin-bottom: .85rem; padding: 1rem; }
.pipeline-top { align-items: flex-start; } .pipeline-title { min-width: 0; }
.pipeline-title h2 { font-size: 1rem; font-weight: 600; color: var(--crm-text-strong); display: flex; align-items: center; gap: .4rem; flex-wrap: wrap; }
.meta { font-size: var(--crm-fs-sm); color: var(--crm-text-muted); margin-top: .6rem; } .meta span { padding: 0 .25rem; }
.pill { display: inline-flex; padding: .15rem .45rem; border-radius: 999px; background: var(--crm-hover); font-size: var(--crm-fs-xs); text-transform: capitalize; }
.pill.default { background: var(--crm-primary-soft); color: var(--crm-primary); } .pill.inactive { color: var(--crm-text-muted); }
.pill.won { background: #ecfdf3; color: #027a48; } .pill.lost { background: #fef3f2; color: #b42318; } .pill.open { background: var(--crm-primary-soft); color: var(--crm-primary); }
.stage-panel { margin-top: 1rem; border-top: 1px solid var(--crm-border); padding-top: 1rem; }
.stage-heading { margin-bottom: .65rem; } .stage-heading h3 { font-size: .95rem; font-weight: 600; }
.stage-row { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: .7rem 0; border-bottom: 1px solid var(--crm-border); }
.stage-name { display: flex; flex-direction: column; gap: .2rem; } .stage-name small { color: var(--crm-text-muted); font-size: var(--crm-fs-sm); }
.drawer-backdrop { position: fixed; inset: 0; z-index: 1050; background: rgba(15, 23, 42, .45); display: flex; justify-content: flex-end; }
.drawer-panel { width: min(520px, 100vw); height: 100%; overflow-y: auto; background: var(--crm-surface); color: var(--crm-text); box-shadow: -12px 0 35px rgba(15, 23, 42, .16); padding: 1.25rem; display: flex; flex-direction: column; gap: 1rem; animation: drawer-in .18s ease-out; }
.drawer-header { padding-bottom: 1rem; border-bottom: 1px solid var(--crm-border); }
.drawer-header h2 { font-size: 1.125rem; font-weight: 600; color: var(--crm-text-strong); }
.drawer-header .close { border: 0; background: transparent; font-size: 1.5rem; min-height: auto; padding: .1rem .4rem; }
.drawer-panel > label { display: flex; flex-direction: column; gap: .35rem; color: var(--crm-text-strong); font-size: var(--crm-fs-sm); font-weight: 500; }
.drawer-panel > label.check { flex-direction: row; align-items: center; font-weight: 400; }
.drawer-footer { margin-top: auto; padding-top: 1rem; border-top: 1px solid var(--crm-border); justify-content: flex-end; }
.alert-error { color: var(--crm-danger, #b42318); background: var(--crm-surface); border: 1px solid var(--crm-border); border-radius: var(--crm-radius); padding: .75rem; }
.empty-state, .empty-inline { color: var(--crm-text-muted); padding: 1.5rem; text-align: center; }
@keyframes drawer-in { from { transform: translateX(12px); opacity: .7; } to { transform: translateX(0); opacity: 1; } }
@media (max-width: 575.98px) { .pipeline-page { padding: .75rem; } .pipeline-top { flex-direction: column; } .actions { flex-wrap: wrap; } }
</style>
