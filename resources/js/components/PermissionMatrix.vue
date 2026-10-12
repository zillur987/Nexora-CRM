<script setup>
import { computed } from 'vue';

const props = defineProps({
    /** Selected permission ids. */
    modelValue: { type: Array, default: () => [] },
    /** [{ module, label, icon, permissions: [{ id, name, action, label }] }] */
    groups: { type: Array, default: () => [] },
    readonly: { type: Boolean, default: false },
    /** Shows everything as granted and not editable (Super Admin). */
    locked: { type: Boolean, default: false },
});
const emit = defineEmits(['update:modelValue']);

const selected = computed(() => new Set(props.modelValue));

// Column order follows the order actions first appear in the registry.
const columns = computed(() => {
    const seen = new Map();
    for (const g of props.groups) for (const p of g.permissions) if (!seen.has(p.action)) seen.set(p.action, p.label);
    return [...seen].map(([action, label]) => ({ action, label }));
});

const cell = (group, action) => group.permissions.find((p) => p.action === action) ?? null;
const isOn = (id) => props.locked || selected.value.has(id);
const disabled = computed(() => props.readonly || props.locked);

const totalCount = computed(() => props.groups.reduce((n, g) => n + g.permissions.length, 0));
const selectedCount = computed(() => (props.locked ? totalCount.value : props.groups.reduce((n, g) => n + g.permissions.filter((p) => selected.value.has(p.id)).length, 0)));

function commit(next) {
    emit('update:modelValue', [...next]);
}

function toggle(id) {
    if (disabled.value) return;
    const next = new Set(selected.value);
    next.has(id) ? next.delete(id) : next.add(id);
    commit(next);
}

function setMany(ids, on) {
    if (disabled.value) return;
    const next = new Set(selected.value);
    for (const id of ids) on ? next.add(id) : next.delete(id);
    commit(next);
}

const rowIds = (group) => group.permissions.map((p) => p.id);
const columnIds = (action) => props.groups.flatMap((g) => g.permissions.filter((p) => p.action === action).map((p) => p.id));

function state(ids) {
    if (props.locked) return { checked: true, indeterminate: false };
    const on = ids.filter((id) => selected.value.has(id)).length;
    return { checked: ids.length > 0 && on === ids.length, indeterminate: on > 0 && on < ids.length };
}

const toggleRow = (group, event) => setMany(rowIds(group), event.target.checked);
const toggleColumn = (action, event) => setMany(columnIds(action), event.target.checked);
const toggleAll = (event) => setMany(props.groups.flatMap(rowIds), event.target.checked);
</script>

<template>
    <div class="perm-matrix">
        <div class="perm-matrix__bar">
            <span class="perm-matrix__count">{{ selectedCount }} of {{ totalCount }} permissions</span>
            <div v-if="!disabled" class="perm-matrix__quick">
                <button type="button" class="btn btn-link btn-sm text-decoration-none p-0" @click="setMany(groups.flatMap(rowIds), true)">Select all</button>
                <span class="text-muted" aria-hidden="true">·</span>
                <button type="button" class="btn btn-link btn-sm text-decoration-none p-0" @click="setMany(groups.flatMap(rowIds), false)">Clear</button>
            </div>
        </div>

        <div class="table-responsive perm-matrix__scroll">
            <table class="table align-middle mb-0 perm-matrix__table">
                <thead>
                    <tr>
                        <th scope="col" class="perm-matrix__module-head">
                            <div class="form-check m-0">
                                <input
                                    id="perm-all"
                                    type="checkbox"
                                    class="form-check-input"
                                    :checked="state(groups.flatMap(rowIds)).checked"
                                    :indeterminate.prop="state(groups.flatMap(rowIds)).indeterminate"
                                    :disabled="disabled"
                                    aria-label="Select every permission"
                                    @change="toggleAll"
                                />
                                <label for="perm-all" class="form-check-label">Module</label>
                            </div>
                        </th>
                        <th v-for="col in columns" :key="col.action" scope="col" class="text-center">
                            <div class="perm-matrix__col">
                                <input
                                    :id="`perm-col-${col.action}`"
                                    type="checkbox"
                                    class="form-check-input"
                                    :checked="state(columnIds(col.action)).checked"
                                    :indeterminate.prop="state(columnIds(col.action)).indeterminate"
                                    :disabled="disabled"
                                    :aria-label="`Select every ${col.label} permission`"
                                    @change="toggleColumn(col.action, $event)"
                                />
                                <label :for="`perm-col-${col.action}`">{{ col.label }}</label>
                            </div>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="g in groups" :key="g.module">
                        <th scope="row" class="perm-matrix__module">
                            <div class="form-check m-0">
                                <input
                                    :id="`perm-row-${g.module}`"
                                    type="checkbox"
                                    class="form-check-input"
                                    :checked="state(rowIds(g)).checked"
                                    :indeterminate.prop="state(rowIds(g)).indeterminate"
                                    :disabled="disabled"
                                    :aria-label="`Select every ${g.label} permission`"
                                    @change="toggleRow(g, $event)"
                                />
                                <label :for="`perm-row-${g.module}`" class="form-check-label">
                                    <i v-if="g.icon" class="bi me-2" :class="g.icon" aria-hidden="true"></i>{{ g.label }}
                                </label>
                            </div>
                        </th>
                        <td v-for="col in columns" :key="col.action" class="text-center">
                            <input
                                v-if="cell(g, col.action)"
                                type="checkbox"
                                class="form-check-input"
                                :checked="isOn(cell(g, col.action).id)"
                                :disabled="disabled"
                                :aria-label="`${col.label} ${g.label}`"
                                @change="toggle(cell(g, col.action).id)"
                            />
                            <span v-else class="perm-matrix__na" aria-hidden="true">—</span>
                        </td>
                    </tr>
                    <tr v-if="!groups.length">
                        <td :colspan="columns.length + 1" class="text-center text-muted py-4">No permissions found. Run <code>php artisan rbac:sync</code>.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>

<style scoped>
.perm-matrix {
    font-family: var(--crm-font, inherit);
    font-size: var(--crm-fs, 0.875rem);
    border: 1px solid var(--crm-border, #e4e7ec);
    border-radius: var(--crm-radius, 0.5rem);
    overflow: hidden;
}

.perm-matrix__bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 0.75rem;
    padding: 0.625rem 0.875rem;
    font-size: var(--crm-fs-sm, 0.8125rem);
    background: var(--crm-bg, #f9fafb);
    border-bottom: 1px solid var(--crm-border, #e4e7ec);
}

.perm-matrix__count {
    font-weight: 500;
    color: var(--crm-text-strong, #344054);
}

.perm-matrix__quick {
    display: flex;
    gap: 0.5rem;
}

.perm-matrix__quick .btn {
    font-size: var(--crm-fs-sm, 0.8125rem);
}

.perm-matrix__scroll {
    max-height: 24rem;
    overflow: auto;
}

.perm-matrix__table {
    --bs-table-bg: transparent;
    font-size: var(--crm-fs, 0.875rem);
}

.perm-matrix__table thead th {
    position: sticky;
    top: 0;
    z-index: 1;
    padding: 0.625rem 0.75rem;
    font-size: var(--crm-fs-sm, 0.8125rem);
    font-weight: 600;
    color: var(--crm-text-strong, #344054);
    white-space: nowrap;
    background: var(--crm-surface, #fff);
    border-bottom: 0;
    box-shadow: inset 0 -1px 0 var(--crm-border, #e4e7ec);
}

.perm-matrix__table tbody th,
.perm-matrix__table tbody td {
    padding: 0.625rem 0.75rem;
    border-bottom: 1px solid var(--crm-border, #e4e7ec);
}

.perm-matrix__table tbody tr:last-child th,
.perm-matrix__table tbody tr:last-child td {
    border-bottom: 0;
}

.perm-matrix__module,
.perm-matrix__module-head {
    min-width: 11rem;
    font-weight: 500;
    white-space: nowrap;
}

.perm-matrix__module .form-check-label {
    font-weight: 500;
    color: var(--crm-text, #101828);
}

.perm-matrix__module .bi {
    color: var(--crm-text-muted, #667085);
}

.perm-matrix__col {
    display: inline-flex;
    align-items: center;
    gap: 0.375rem;
}

.perm-matrix__col label {
    margin: 0;
    cursor: pointer;
}

.perm-matrix__na {
    color: var(--crm-text-muted, #667085);
    opacity: 0.5;
}

.perm-matrix .form-check-input:not(:disabled) {
    cursor: pointer;
}
</style>
