<script setup>
import { ref, computed } from 'vue';
import { useBannerContent } from '../composables/useSiteContent.js';
import { imgVarUrl, bgUrl } from '../utils/asset.js';
import { prefersReducedMotion as reduce, pad } from '../utils/media.js';
import { useEventListener } from '../composables/useEventListener.js';
import { useInView } from '../composables/useInView.js';

/**
 * Spotlight slider: cross-fade slides with Ken Burns zoom and masked title reveal.
 * Autoplay is driven by the CSS progress bar (animationend), so the bar and the timing never drift.
 * Pauses on hover/focus, when off-screen, when the tab is hidden or when the user presses pause.
 * Supports arrows, tabs, keyboard (left/right), swipe/drag and respects reduced motion.
 */
const props = defineProps({ interval: { type: Number, default: 7000 } });

const root = ref(null);
const index = ref(0);
const hover = ref(false);
const hidden = ref(false);
const paused = ref(false);
const banner = useBannerContent();   // slides come from the CMS API (Banner Slider)
const slides = computed(() => banner.data.slides);
const count = computed(() => slides.value.length);

const inView = useInView(root, { threshold: 0.35 });
useEventListener(() => document, 'visibilitychange', () => { hidden.value = document.hidden; });

const next = computed(() => slides.value[(index.value + 1) % count.value] ?? {});
const live = computed(() => `Slide ${index.value + 1} of ${count.value}: ${slides.value[index.value]?.name ?? ''}`);

const show = (to) => { if (count.value) index.value = (to + count.value) % count.value; };

const onKey = (e) => {
  if (e.key === 'ArrowRight') { show(index.value + 1); e.preventDefault(); }
  if (e.key === 'ArrowLeft') { show(index.value - 1); e.preventDefault(); }
};
// the active tab's bar finishing advances the slide
const onAnimationEnd = (e) => { if (e.animationName === 'spot-fill') show(index.value + 1); };

/* swipe / drag */
let startX = null;
const onPointerDown = (e) => {
  if (e.target.closest('a, button')) return;
  startX = e.clientX;
  e.currentTarget.setPointerCapture?.(e.pointerId);
  document.documentElement.classList.add('cursor-down');
};
const onPointerCancel = () => { startX = null; };
const onPointerUp = (e) => {
  document.documentElement.classList.remove('cursor-down');
  if (startX === null) return;
  const dx = e.clientX - startX;
  startX = null;
  if (Math.abs(dx) > 50) show(index.value + (dx < 0 ? 1 : -1));
};
</script>

<template>
  <section
    v-show="count"
    ref="root"
    class="spot"
    :class="{
      'is-auto': !reduce,
      'is-hover': hover,
      'is-hidden': hidden,
      'is-paused': paused,
      'is-offscreen': !inView,
    }"
    :style="{ '--autoplay': `${interval}ms`, '--n': count }"
    id="spotlight"
    aria-roledescription="carousel"
    aria-label="Signature residences"
    @keydown="onKey"
    @focusin="hover = true"
    @focusout="hover = false"
    @animationend="onAnimationEnd"
  >
    <h2 class="visually-hidden">Signature residences</h2>
    <p class="visually-hidden" :aria-live="paused ? 'polite' : 'off'">{{ live }}</p>

    <div
      class="spot__stage"
      data-cursor="Drag"
      @mouseenter="hover = true"
      @mouseleave="hover = false"
      @pointerdown="onPointerDown"
      @pointerup="onPointerUp"
      @pointercancel="onPointerCancel"
    >
      <article
        v-for="(s, i) in slides"
        :key="s.id"
        class="spot__slide"
        :class="{ 'is-active': i === index }"
        role="group"
        aria-roledescription="slide"
        :aria-label="`${i + 1} of ${count}`"
        :aria-hidden="String(i !== index)"
        :inert="i !== index"
      >
        <span class="spot__img ph" :data-grad="s.gradient" :style="imgVarUrl(s.image_url)"></span>
        <div class="spot__copy">
          <p v-if="s.location" class="spot__loc">{{ s.location }}</p>
          <h3 class="spot__title">
            <span v-for="(line, n) in s.lines" :key="n" class="spot__ln" :style="{ '--l': n }"><span>{{ line }}</span></span>
          </h3>
          <p v-if="s.text" class="spot__text">{{ s.text }}</p>
          <ul v-if="s.facts.length" class="spot__facts"><li v-for="fact in s.facts" :key="fact.label"><b>{{ fact.value }}</b><span>{{ fact.label }}</span></li></ul>
          <div v-if="s.cta_label" class="spot__cta"><a v-magnetic class="btn-gold" :href="s.cta_url">{{ s.cta_label }} <i class="bi bi-arrow-right" aria-hidden="true"></i></a></div>
        </div>
      </article>

      <p class="spot__count" aria-hidden="true"><b>{{ pad(index + 1) }}</b> / <span>{{ pad(count) }}</span></p>

      <div class="spot__ui">
        <div class="spot__tabs" role="tablist" aria-label="Choose a residence">
          <button
            v-for="(s, i) in slides"
            :key="s.id"
            class="spot__tab"
            type="button"
            role="tab"
            :aria-selected="String(i === index)"
            :tabindex="i === index ? 0 : -1"
            @click="show(i)"
          ><i class="spot__bar" aria-hidden="true"></i><small>{{ pad(i + 1) }}</small><span>{{ s.name }}</span></button>
        </div>
        <div class="spot__side">
          <button class="spot__next" type="button" aria-label="Next slide" @click="show(index + 1)">
            <span class="spot__next-img" :style="bgUrl(next.image_url)"></span>
            <span><small>Next</small><strong>{{ next.name }}</strong></span>
          </button>
          <div class="spot__ctrl">
            <button class="spot__btn" type="button" aria-label="Previous slide" @click="show(index - 1)"><i class="bi bi-arrow-left" aria-hidden="true"></i></button>
            <button
              class="spot__btn"
              type="button"
              :aria-pressed="String(paused)"
              :aria-label="paused ? 'Resume automatic slides' : 'Pause automatic slides'"
              @click="paused = !paused"
            ><i class="bi" :class="paused ? 'bi-play-fill' : 'bi-pause-fill'" aria-hidden="true"></i></button>
            <button class="spot__btn" type="button" aria-label="Next slide" @click="show(index + 1)"><i class="bi bi-arrow-right" aria-hidden="true"></i></button>
          </div>
        </div>
      </div>
    </div>
  </section>
</template>
