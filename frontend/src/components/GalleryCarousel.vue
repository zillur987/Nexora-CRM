<script setup>
import { ref, onMounted } from 'vue';
import { galleryItems } from '../data/sections.js';
import { imgVar } from '../utils/asset.js';
import { prefersReducedMotion as reduce, scrollBehavior as behavior } from '../utils/media.js';
import { useEventListener } from '../composables/useEventListener.js';
import { useRafThrottle } from '../composables/useRafThrottle.js';

/**
 * Gallery carousel: arrows, keyboard, mouse-drag, progress bar and a subtle inner-image parallax
 * (each image shifts against the scroll position). Native scroll-snap keeps touch behaviour perfect.
 * Per-frame values (--p, --px) are written straight to the DOM; Vue state only drives the arrows.
 */
const track = ref(null);
const bar = ref(null);
const prevDisabled = ref(true);
const nextDisabled = ref(false);
const dragging = ref(false);

const items = () => [...(track.value?.children ?? [])];

const step = () => {
  const gap = parseFloat(getComputedStyle(track.value).columnGap) || 0;
  return items()[0].getBoundingClientRect().width + gap;
};

const update = () => {
  const t = track.value;
  if (!t) return;
  const max = t.scrollWidth - t.clientWidth;
  const p = max > 1 ? t.scrollLeft / max : 1;
  const view = t.clientWidth / t.scrollWidth;
  bar.value?.style.setProperty('--p', Math.min(1, Math.max(view + (1 - view) * p, 0.1)));
  prevDisabled.value = t.scrollLeft <= 2;
  nextDisabled.value = t.scrollLeft >= max - 2;
  if (!reduce) {
    const tr = t.getBoundingClientRect();
    const mid = tr.left + tr.width / 2;
    items().forEach((it) => {
      const r = it.getBoundingClientRect();
      if (r.right < tr.left - 100 || r.left > tr.right + 100) return;
      const px = Math.max(-1, Math.min(1, (r.left + r.width / 2 - mid) / (tr.width / 2 + r.width / 2)));
      it.style.setProperty('--px', px.toFixed(3));
    });
  }
};
const queue = useRafThrottle(update);
useEventListener(() => window, 'resize', queue, { passive: true });

const go = (dir) => track.value.scrollBy({ left: dir * step(), behavior });
const onKey = (e) => {
  if (e.key === 'ArrowRight') { go(1); e.preventDefault(); }
  if (e.key === 'ArrowLeft') { go(-1); e.preventDefault(); }
};

/* mouse drag (touch and pen use native scrolling) */
let down = false, moved = 0, startX = 0, startLeft = 0;
const onPointerDown = (e) => {
  if (e.pointerType !== 'mouse' || e.button !== 0) return;
  down = true; moved = 0; startX = e.clientX; startLeft = track.value.scrollLeft;
};
useEventListener(() => window, 'pointermove', (e) => {
  if (!down) return;
  const dx = e.clientX - startX;
  moved = Math.max(moved, Math.abs(dx));
  if (moved > 5) dragging.value = true;
  track.value.scrollLeft = startLeft - dx;
});
const release = () => {
  if (!down) return;
  down = false;
  dragging.value = false;
  // settle on the nearest card
  const t = track.value;
  const tr = t.getBoundingClientRect();
  const pad = parseFloat(getComputedStyle(t).paddingLeft);
  const target = items().reduce((best, it) => {
    const d = Math.abs(it.getBoundingClientRect().left - tr.left - pad);
    return d < best.d ? { it, d } : best;
  }, { it: items()[0], d: Infinity }).it;
  target.scrollIntoView({ behavior, inline: 'start', block: 'nearest' });
};
useEventListener(() => window, 'pointerup', release);
useEventListener(() => window, 'pointercancel', release);

onMounted(update);
</script>

<template>
  <section class="gal sx sx--paper" id="gallery" aria-labelledby="gal-title">
    <header class="sx__head" v-reveal>
      <div>
        <p class="eyebrow eyebrow--dark">Life at Aurelia</p>
        <h2 class="sx__title" id="gal-title">Spaces made for slow evenings</h2>
      </div>
      <div class="gal__tools">
        <div class="arrows">
          <button type="button" class="arrows__btn" :disabled="prevDisabled" aria-label="Previous photos" @click="go(-1)"><i class="bi bi-arrow-left" aria-hidden="true"></i></button>
          <button type="button" class="arrows__btn" :disabled="nextDisabled" aria-label="Next photos" @click="go(1)"><i class="bi bi-arrow-right" aria-hidden="true"></i></button>
        </div>
      </div>
    </header>
    <ul
      ref="track"
      class="gal__track"
      :class="{ 'is-dragging': dragging }"
      data-cursor="Drag"
      tabindex="0"
      aria-label="Photo gallery, scrollable"
      @scroll.passive="queue"
      @keydown="onKey"
      @pointerdown="onPointerDown"
    >
      <li v-for="g in galleryItems" :key="g.img" class="gal__item" :class="g.mod && `gal__item--${g.mod}`">
        <figure>
          <span class="gal__frame"><span class="gal__img ph" :data-grad="g.grad" :style="imgVar(`img/${g.img}`)"></span></span>
          <figcaption><strong>{{ g.title }}</strong><span>{{ g.place }}</span></figcaption>
        </figure>
      </li>
    </ul>
    <div class="gal__progress" aria-hidden="true"><span ref="bar"></span></div>
  </section>
</template>
