<script setup>
import { computed, ref, useId, nextTick } from 'vue';

/**
 * Accessible custom listbox. v-model holds the selected value ('' = Any).
 * Keyboard: Arrow keys / Home / End move, Enter / Space select, Esc closes, Tab closes.
 */
const props = defineProps({
  modelValue: { type: String, default: '' },
  open: { type: Boolean, default: false },
  name: { type: String, required: true },
  label: { type: String, required: true },
  options: { type: Array, required: true }, // [[value, label], ...]
  wide: { type: Boolean, default: false },
});
const emit = defineEmits(['update:modelValue', 'update:open']);

const uid = useId();
const labelId = `lbl-${uid}`;
const valueId = `val-${uid}`;
const trigger = ref(null);
const menu = ref(null);

const selectedLabel = computed(() => props.options.find(([v]) => v === props.modelValue)?.[1] ?? props.options[0][1]);
const optionEls = () => [...(menu.value?.children ?? [])];

const focusOption = (index) => {
  const els = optionEls();
  const i = Math.max(0, Math.min(index < 0 ? 0 : index, els.length - 1));
  els[i]?.focus();
};

const open = async () => {
  emit('update:open', true);
  await nextTick();
  focusOption(props.options.findIndex(([v]) => v === props.modelValue));
};
const close = ({ returnFocus = false } = {}) => {
  emit('update:open', false);
  if (returnFocus) trigger.value?.focus();
};
const select = (value) => {
  emit('update:modelValue', value);
  close({ returnFocus: true });
};

const onTriggerKey = (e) => {
  if (e.key === 'ArrowDown' || e.key === 'ArrowUp') { e.preventDefault(); open(); }
};

const onMenuKey = (e) => {
  const els = optionEls();
  const current = els.indexOf(document.activeElement);
  switch (e.key) {
    case 'ArrowDown': e.preventDefault(); focusOption(current + 1); break;
    case 'ArrowUp':   e.preventDefault(); focusOption(current - 1); break;
    case 'Home':      e.preventDefault(); focusOption(0); break;
    case 'End':       e.preventDefault(); focusOption(els.length - 1); break;
    case 'Enter':
    case ' ':         e.preventDefault(); if (current >= 0) select(props.options[current][0]); break;
    case 'Escape':    e.preventDefault(); close({ returnFocus: true }); break;
    case 'Tab':       close(); break;
    default:
  }
};
</script>

<template>
  <div class="search-bar__field" :class="{ 'search-bar__field--wide': wide }">
    <span class="search-bar__label" :id="labelId">{{ label }}</span>
    <button
      ref="trigger"
      type="button"
      class="search-bar__trigger"
      aria-haspopup="listbox"
      :aria-expanded="String(props.open)"
      :aria-labelledby="`${labelId} ${valueId}`"
      @click="props.open ? close() : open()"
      @keydown="onTriggerKey"
    >
      <span :id="valueId">{{ selectedLabel }}</span>
      <i class="bi bi-chevron-down" aria-hidden="true"></i>
    </button>
    <ul
      ref="menu"
      class="search-bar__menu"
      role="listbox"
      :aria-labelledby="labelId"
      :hidden="!props.open"
      @keydown="onMenuKey"
    >
      <li
        v-for="([value, text], i) in options"
        :id="`${uid}-opt-${i}`"
        :key="value"
        role="option"
        tabindex="-1"
        :aria-selected="String(value === modelValue)"
        @click="select(value)"
      >{{ text }}</li>
    </ul>
    <!-- Empty values are disabled so they stay out of the GET query string. -->
    <input type="hidden" :name="name" :value="modelValue" :disabled="modelValue === ''">
  </div>
</template>
