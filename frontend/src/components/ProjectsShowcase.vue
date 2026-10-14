<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import CountUp from './CountUp.vue';
import { projects, projectFilters, figures } from '../data/projects.js';
import { imgVar } from '../utils/asset.js';
import { prefersReducedMotion as reduce, scrollBehavior as behavior, pad } from '../utils/media.js';
import { useEventListener } from '../composables/useEventListener.js';
import { useRafThrottle } from '../composables/useRafThrottle.js';

/**
 * Featured projects: hover-only autoplay, status filter, arrows + keyboard + mouse-drag scrolling,
 * progress bar / counter. Scroll-driven numbers are measured from the DOM; everything the template
 * shows (filter, counters, disabled arrows, pause state) is reactive state.
 */
const props = defineProps({ autoplay: { type: Number, default: 2500 } });

const track = ref(null);
const bar = ref(null);
const controls = ref(null);

const filter = ref('all');
const userPaused = ref(false);
const live = ref('');
const current = ref(0);
const prevDisabled = ref(true);
const nextDisabled = ref(false);

const isShown = (p) => filter.value === 'all' || p.status === filter.value;
const shownCount = computed(() => projects.filter(isShown).length);
const total = computed(() => pad(shownCount.value));

const cards = () => [...(track.value?.children ?? [])];
const visible = () => cards().filter((c) => !c.hidden);
const maxScroll = () => track.value.scrollWidth - track.value.clientWidth;
const step = () => {
  const first = visible()[0];
  if (!first) return 0;
  const gap = parseFloat(getComputedStyle(track.value).columnGap) || 0;
  return first.getBoundingClientRect().width + gap;
};

/* ---- progress + arrow state ---- */
const update = () => {
  const t = track.value;
  if (!t) return;
  const max = maxScroll();
  const p = max > 1 ? t.scrollLeft / max : 1;
  const list = visible();
  // number of the last card fully inside the viewport (reads "06 / 06" at the end)
  const right = t.getBoundingClientRect().right + 2;
  const idx = list.filter((c) => c.getBoundingClientRect().right <= right).length;
  bar.value?.style.setProperty('--p', Math.max(p, list.length ? 1 / list.length : 0));
  current.value = list.length ? idx : 0;
  prevDisabled.value = t.scrollLeft <= 2;
  nextDisabled.value = t.scrollLeft >= max - 2;
};
const queue = useRafThrottle(update);
useEventListener(() => window, 'resize', queue, { passive: true });

// Arrow clicks move one card; rapid clicks stack from the pending target instead of the mid-animation position
let pending = null;
let pendingTimer;
const go = (dir) => {
  const t = track.value;
  const base = pending ?? t.scrollLeft;
  pending = Math.max(0, Math.min(maxScroll(), base + dir * step()));
  t.scrollTo({ left: pending, behavior });
  clearTimeout(pendingTimer);
  pendingTimer = setTimeout(() => { pending = null; }, 700);
};

const onTrackKey = (e) => {
  if (e.target !== track.value) return;
  if (e.key === 'ArrowRight') { e.preventDefault(); go(1); }
  if (e.key === 'ArrowLeft') { e.preventDefault(); go(-1); }
};

/* ---- filter ---- */
const setFilter = (value) => {
  filter.value = value;
  // cards re-render with [hidden] first; then re-measure
  requestAnimationFrame(() => {
    track.value.scrollTo({ left: 0, behavior: 'auto' });
    update();
    const n = shownCount.value;
    live.value = `${n} project${n === 1 ? '' : 's'} shown`;
  });
};

/* ---- mouse drag (touch/pen use native scrolling) ---- */
const dragClass = ref(false);
let startX = 0, startLeft = 0, dragging = false, moved = false;
const onPointerDown = (e) => {
  if (e.pointerType !== 'mouse' || e.button !== 0) return;
  dragging = true; moved = false; startX = e.clientX; startLeft = track.value.scrollLeft;
};
useEventListener(() => window, 'pointermove', (e) => {
  if (!dragging) return;
  const dx = e.clientX - startX;
  if (!moved && Math.abs(dx) > 5) { moved = true; dragClass.value = true; }
  if (moved) track.value.scrollLeft = startLeft - dx;
});
const endDrag = () => {
  if (!dragging) return;
  dragging = false;
  if (moved) {
    dragClass.value = false;
    const s = step(); // let snap settle on the nearest card
    if (s) track.value.scrollTo({ left: Math.round(track.value.scrollLeft / s) * s, behavior });
  }
};
useEventListener(() => window, 'pointerup', endDrag);
useEventListener(() => window, 'pointercancel', endDrag);

/* ---- hover-only autoplay: slides while a mouse pointer is over the cards or arrows, stops when it leaves.
   No autoplay for touch or reduced-motion users. ---- */
let timer = 0;
let hovering = false;
const canRun = () => hovering && !userPaused.value && !reduce && !document.hidden && !dragging;
const schedule = () => {
  clearTimeout(timer);
  if (!canRun()) return;
  timer = setTimeout(() => {
    const max = maxScroll();
    if (max > 2) {
      if (track.value.scrollLeft >= max - 2) track.value.scrollTo({ left: 0, behavior });
      else go(1);
    }
    schedule();
  }, props.autoplay);
};
const onEnter = (e) => { if (e.pointerType === 'mouse') { hovering = true; schedule(); } };
const onLeave = (e) => { if (e.pointerType === 'mouse') { hovering = false; schedule(); } };
const togglePause = () => { userPaused.value = !userPaused.value; schedule(); };

if (!reduce) {
  useEventListener(() => window, 'pointerup', schedule);
  useEventListener(() => document, 'visibilitychange', schedule);
}

onMounted(update);
onBeforeUnmount(() => { clearTimeout(timer); clearTimeout(pendingTimer); });
</script>

<template>
  <section class="showcase" id="projects" aria-labelledby="showcase-title" data-showcase>
    <div class="showcase__head" v-reveal>
      <div>
        <p class="eyebrow eyebrow--dark">Featured projects</p>
        <h2 class="showcase__title" id="showcase-title">Residences shaped by their place</h2>
      </div>

      <div class="showcase__tools">
        <div class="filters" role="group" aria-label="Filter projects by status">
          <button
            v-for="f in projectFilters"
            :key="f.value"
            type="button"
            class="filters__btn"
            :aria-pressed="String(filter === f.value)"
            @click="setFilter(f.value)"
          >{{ f.label }}</button>
        </div>
        <div ref="controls" class="arrows" @pointerenter="onEnter" @pointerleave="onLeave">
          <button type="button" class="arrows__btn" :disabled="prevDisabled" aria-label="Previous projects" @click="go(-1)"><i class="bi bi-arrow-left" aria-hidden="true"></i></button>
          <button
            v-if="!reduce"
            type="button"
            class="arrows__btn"
            :aria-pressed="String(userPaused)"
            :aria-label="userPaused ? 'Resume sliding on hover' : 'Pause sliding on hover'"
            @click="togglePause"
          ><i class="bi" :class="userPaused ? 'bi-play-fill' : 'bi-pause-fill'" aria-hidden="true"></i></button>
          <button type="button" class="arrows__btn" :disabled="nextDisabled" aria-label="Next projects" @click="go(1)"><i class="bi bi-arrow-right" aria-hidden="true"></i></button>
        </div>
      </div>
    </div>

    <ul
      ref="track"
      class="showcase__track"
      :class="{ 'is-dragging': dragClass }"
      tabindex="0"
      aria-label="Featured projects, scrollable"
      @scroll.passive="queue"
      @keydown="onTrackKey"
      @pointerdown="onPointerDown"
      @pointerenter="onEnter"
      @pointerleave="onLeave"
    >
      <li
        v-for="(p, i) in projects"
        :key="p.slug"
        class="card-p"
        :data-status="p.status"
        :hidden="!isShown(p)"
        :style="{ '--i': i }"
      >
        <a class="card-p__link" :href="`#project-${p.slug}`" :aria-label="`${p.title}, ${p.type}, from ${p.price}`">
          <div class="card-p__media" :data-grad="p.grad">
            <span class="card-p__img" :style="imgVar(`img/${p.img}`)"></span>
            <span class="card-p__badge">{{ p.badge }}</span>
            <span class="card-p__index" aria-hidden="true">{{ pad(i + 1) }}</span>
            <span class="card-p__price"><small>From</small>{{ p.price }}</span>
          </div>
          <div class="card-p__body">
            <div>
              <p class="card-p__type">{{ p.type }}</p>
              <h3 class="card-p__title">{{ p.title }}</h3>
              <p class="card-p__meta"><span v-for="m in p.meta" :key="m">{{ m }}</span></p>
            </div>
            <span class="card-p__go" aria-hidden="true"><i class="bi bi-arrow-up-right"></i></span>
          </div>
        </a>
      </li>
    </ul>

    <div class="showcase__foot" v-reveal>
      <p class="showcase__count" aria-hidden="true"><b>{{ pad(current) }}</b> / <span>{{ total }}</span></p>
      <div class="showcase__bar" aria-hidden="true"><span ref="bar"></span></div>
      <a class="btn-outline-dark" href="#projects-all">View all projects <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
    </div>
    <p class="visually-hidden" role="status" aria-live="polite">{{ live }}</p>

    <!-- Key figures -->
    <div class="figures" aria-label="Key figures">
      <dl class="figures__grid">
        <div v-for="f in figures" :key="f.label" class="figures__item" v-reveal>
          <dt>{{ f.label }}</dt>
          <dd><CountUp :end="f.value" :group="f.group" /><sup v-if="f.suffix">{{ f.suffix }}</sup></dd>
        </div>
      </dl>
    </div>
  </section>
</template>
