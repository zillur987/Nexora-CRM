<script setup>
import { reactive, ref } from 'vue';
import SearchDropdown from './SearchDropdown.vue';
import { searchFields, statusChips } from '../data/search.js';
import { useEventListener } from '../composables/useEventListener.js';

/** Search form: one dropdown open at a time, closes on outside click, submits a clean GET query. */
const values = reactive(Object.fromEntries(searchFields.map((f) => [f.name, ''])));
const openName = ref(null);
const form = ref(null);

const setOpen = (name, isOpen) => {
  if (isOpen) openName.value = name;
  else if (openName.value === name) openName.value = null;
};

useEventListener(() => document, 'click', (e) => {
  // Clicks inside a dropdown are handled by that dropdown; anything else closes the open one.
  if (!e.target.closest?.('.search-bar__field')) openName.value = null;
});
</script>

<template>
  <form ref="form" class="search-bar" id="search" action="properties.html" method="get" role="search">

    <!-- Advanced filters (Bootstrap collapse) -->
    <div class="collapse search-bar__advanced" id="advancedFilters">
      <fieldset class="search-bar__chips">
        <legend class="search-bar__label">Status</legend>
        <label v-for="chip in statusChips" :key="chip.value" class="chip">
          <input type="checkbox" name="status" :value="chip.value"><span>{{ chip.label }}</span>
        </label>
      </fieldset>
    </div>

    <div class="search-bar__row">
      <SearchDropdown
        v-for="field in searchFields"
        :key="field.name"
        v-model="values[field.name]"
        :open="openName === field.name"
        :name="field.name"
        :label="field.label"
        :options="field.options"
        :wide="field.wide"
        @update:open="setOpen(field.name, $event)"
      />

      <button class="search-bar__filters" type="button" data-bs-toggle="collapse"
              data-bs-target="#advancedFilters" aria-expanded="false" aria-controls="advancedFilters"
              aria-label="More filters">
        <i class="bi bi-sliders" aria-hidden="true"></i>
      </button>

      <button class="search-bar__submit" type="submit">Search</button>
    </div>
  </form>
</template>
