<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue';
import { prefersReducedMotion, hasIO } from '../utils/media.js';

/** Counts up from 0 once 60% visible. Renders the final value without IntersectionObserver / with reduced motion. */
const props = defineProps({
  end: { type: Number, required: true },
  group: { type: Boolean, default: false }, // thousands separators
  duration: { type: Number, default: 1600 },
});

const el = ref(null);
const value = ref(props.end);
const format = (v) => (props.group ? v.toLocaleString('en-US') : String(v));

let io;
let raf = 0;
onMounted(() => {
  if (prefersReducedMotion || !hasIO) return;
  value.value = 0;
  io = new IntersectionObserver(([entry]) => {
    if (!entry.isIntersecting) return;
    io.disconnect();
    const t0 = performance.now();
    const tick = (t) => {
      const k = Math.min(1, (t - t0) / props.duration);
      value.value = Math.round(props.end * (1 - (1 - k) ** 3));
      if (k < 1) raf = requestAnimationFrame(tick);
    };
    raf = requestAnimationFrame(tick);
  }, { threshold: 0.6 });
  io.observe(el.value);
});
onBeforeUnmount(() => { io?.disconnect(); cancelAnimationFrame(raf); });
</script>

<template>
  <span ref="el">{{ format(value) }}</span>
</template>
