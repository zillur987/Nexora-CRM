<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue';
import { hasFinePointer } from '../utils/media.js';

/** "Drag" cursor bubble that follows the mouse over elements marked with data-cursor="Drag". */
const cur = ref(null);
const label = ref('');
const on = ref(false);
const down = ref(false);

let x = 0, y = 0, tx = 0, ty = 0, raf = 0;
const loop = () => {
  x += (tx - x) * 0.2; y += (ty - y) * 0.2;
  cur.value?.style.setProperty('--cx', `${x.toFixed(1)}px`);
  cur.value?.style.setProperty('--cy', `${y.toFixed(1)}px`);
  raf = Math.abs(tx - x) + Math.abs(ty - y) > 0.5 ? requestAnimationFrame(loop) : 0;
};
const onMove = (e) => {
  if (e.pointerType !== 'mouse') return;
  tx = e.clientX; ty = e.clientY;
  const host = e.target.closest?.('[data-cursor]');
  on.value = !!host && !e.target.closest('a, button');
  if (host) label.value = host.dataset.cursor;
  if (!raf) raf = requestAnimationFrame(loop);
};
const onLeave = () => { on.value = false; };
const onDown = () => { down.value = true; };
const onUp = () => { down.value = false; };

onMounted(() => {
  if (!hasFinePointer) return;
  document.addEventListener('pointermove', onMove, { passive: true });
  document.addEventListener('pointerleave', onLeave);
  document.addEventListener('pointerdown', onDown);
  document.addEventListener('pointerup', onUp);
});
onBeforeUnmount(() => {
  cancelAnimationFrame(raf);
  document.removeEventListener('pointermove', onMove);
  document.removeEventListener('pointerleave', onLeave);
  document.removeEventListener('pointerdown', onDown);
  document.removeEventListener('pointerup', onUp);
});
</script>

<template>
  <div ref="cur" class="cursor" :class="{ 'is-on': on, 'is-down': down }" aria-hidden="true">{{ label }}</div>
</template>
