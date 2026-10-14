<script setup>
import { ref, onMounted } from 'vue';
import { useEventListener } from '../composables/useEventListener.js';
import { useRafThrottle } from '../composables/useRafThrottle.js';

/** Thin gold reading-progress bar at the top of the page. */
const bar = ref(null);

const update = () => {
  const max = document.documentElement.scrollHeight - window.innerHeight;
  bar.value?.style.setProperty('--scroll', max > 0 ? Math.min(1, window.scrollY / max).toFixed(4) : 0);
};
const queue = useRafThrottle(update);
useEventListener(() => window, 'scroll', queue, { passive: true });
onMounted(update);
</script>

<template>
  <div class="page-progress" aria-hidden="true"><span ref="bar"></span></div>
</template>
