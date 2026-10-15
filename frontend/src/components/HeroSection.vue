<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue';

import SearchBar from './SearchBar.vue';
import { useHeaderSentinel } from '../composables/useHeaderState.js';
import { useBannerContent } from '../composables/useSiteContent.js';
import { bgUrl } from '../utils/asset.js';
import { prefersReducedMotion, hasIO } from '../utils/media.js';

const props = defineProps({
  videoSrc: { type: String, default: '' },
  poster: { type: String, default: '' },
  interval: { type: Number, default: 5000 },
});

const banner = useBannerContent();

// API shape: { hero: { headline, video_url, poster_url, show_search }, slides: [...] }
const hero = computed(() => banner.data.hero ?? {});

const slides = computed(() => {
  const items = banner.data.slides;
  return Array.isArray(items) ? items.filter((slide) => slide.image_url) : [];
});

const hasSlides = computed(() => slides.value.length > 0);
const activeIndex = ref(0);
const isPaused = ref(false);
const activeSlide = computed(() => slides.value[activeIndex.value] ?? null);

const videoUrl = computed(() => props.videoSrc || hero.value.video_url || '');
const posterUrl = computed(() => props.poster || hero.value.poster_url || '');

// Poster is the background only when there are no image slides.
const mediaStyle = computed(() =>
  !hasSlides.value && posterUrl.value
    ? { ...bgUrl(posterUrl.value), backgroundSize: 'cover', backgroundPosition: 'center' }
    : {},
);

const video = ref(null);
const sentinel = ref(null);
useHeaderSentinel(sentinel);

let timer = null;
let io = null;

function goToSlide(index) {
  const total = slides.value.length;
  if (!total) return;
  activeIndex.value = ((index % total) + total) % total;
}

function stopSlider() {
  clearInterval(timer);
  timer = null;
}

function startSlider() {
  stopSlider();
  if (slides.value.length < 2 || isPaused.value || prefersReducedMotion) return;
  timer = setInterval(() => goToSlide(activeIndex.value + 1), Math.max(1000, props.interval));
}

// User actions also restart the timer so the next slide is not shown right after a click.
function go(index) {
  goToSlide(index);
  startSlider();
}

function togglePause() {
  isPaused.value = !isPaused.value;
  startSlider(); // stops when paused, starts when resumed
}

function startVideo() {
  const el = video.value;
  if (!el || hasSlides.value || !videoUrl.value || prefersReducedMotion || navigator.connection?.saveData === true) return;

  el.muted = true;
  if (posterUrl.value) el.poster = posterUrl.value;
  el.src = videoUrl.value;

  el.addEventListener('canplay', () => {
    el.classList.add('is-ready');
    el.play().catch(() => {});
  }, { once: true });
  el.addEventListener('error', () => el.classList.remove('is-ready'), { once: true });
  el.load();

  if (hasIO) {
    io?.disconnect();
    io = new IntersectionObserver(([entry]) => {
      if (!el.classList.contains('is-ready')) return;
      if (entry.isIntersecting) el.play().catch(() => {});
      else el.pause();
    });
    io.observe(el);
  }
}

// Slides arrive with the API response: keep the index valid and (re)start autoplay.
watch(slides, (items) => {
  if (activeIndex.value >= items.length) activeIndex.value = 0;
  startSlider();
}, { immediate: true });

watch(() => banner.loaded, (loaded) => { if (loaded) startVideo(); }, { immediate: true, flush: 'post' });

onMounted(() => {
  startSlider();
  startVideo();
});

onBeforeUnmount(() => {
  stopSlider();
  io?.disconnect();
});
</script>

<template>
  <section class="hero" aria-label="Find your property" data-hero>
    <div class="hero__media" :style="mediaStyle" aria-hidden="true">
      <!-- Image slides from the CMS -->
      <div v-if="hasSlides" class="hero__slides">
        <div
          v-for="(slide, index) in slides"
          :key="slide.id"
          class="hero__slide"
          :class="{ 'is-active': index === activeIndex }"
          :style="bgUrl(slide.image_url)"
        />
      </div>

      <!-- Video fallback when there are no image slides -->
      <video v-else ref="video" class="hero__video" muted loop playsinline preload="none" />

      <div v-if="!hasSlides && !posterUrl" class="hero__bokeh" />
      <div class="hero__scrim" />
    </div>

    <!-- Header scroll boundary -->
    <div ref="sentinel" class="hero__end" aria-hidden="true" />

    <div class="hero__inner">
      <div class="hero__content">
        <h1 class="hero__headline">{{ hero.headline }}</h1>

        <p v-if="activeSlide?.lines?.length" class="hero__slide-title">
          {{ activeSlide.lines.join(' ') }}
        </p>

        <a v-if="activeSlide?.cta_label" class="btn-gold hero__cta" :href="activeSlide.cta_url">
          {{ activeSlide.cta_label }} <i class="bi bi-arrow-right" aria-hidden="true"></i>
        </a>

        <SearchBar v-if="hero.show_search" />
      </div>
    </div>

    <!-- Slider controls -->
    <div v-if="slides.length > 1" class="hero__controls" role="group" aria-label="Banner slideshow controls">
      <button type="button" class="hero__arrow" aria-label="Previous banner" @click="go(activeIndex - 1)">
        <i class="bi bi-arrow-left" aria-hidden="true"></i>
      </button>

      <div class="hero__pagination">
        <button
          v-for="(slide, index) in slides"
          :key="slide.id"
          type="button"
          class="hero__dot"
          :class="{ 'is-active': index === activeIndex }"
          :aria-label="`Go to banner ${index + 1}`"
          :aria-current="index === activeIndex ? 'true' : undefined"
          @click="go(index)"
        />
      </div>

      <span class="hero__counter" aria-hidden="true">
        {{ String(activeIndex + 1).padStart(2, '0') }} / {{ String(slides.length).padStart(2, '0') }}
      </span>

      <button type="button" class="hero__arrow" :aria-label="isPaused ? 'Play slideshow' : 'Pause slideshow'" @click="togglePause">
        <i class="bi" :class="isPaused ? 'bi-play-fill' : 'bi-pause-fill'" aria-hidden="true"></i>
      </button>

      <button type="button" class="hero__arrow" aria-label="Next banner" @click="go(activeIndex + 1)">
        <i class="bi bi-arrow-right" aria-hidden="true"></i>
      </button>
    </div>
  </section>
</template>
<style>
/* ========================================
   HERO SLIDER — IMAGE TRANSITIONS
======================================== */

.hero {
  position: relative;
  isolation: isolate;
  overflow: hidden;
}

.hero__media {
  position: absolute;
  inset: 0;
  z-index: -1;
  overflow: hidden;
  background-color: #17212b;
}

.hero__slides,
.hero__slide {
  position: absolute;
  inset: 0;
}

.hero__slide {
  background-position: center;
  background-repeat: no-repeat;
  background-size: cover;
  opacity: 0;
  transform: scale(1.025);
  transition:
    opacity 900ms ease-in-out,
    transform 5s ease;
  will-change: opacity;
  pointer-events: none;
}

.hero__slide.is-active {
  opacity: 1;
  transform: scale(1);
  pointer-events: auto;
}

/* Optional overlay for readable text */
.hero__scrim {
  position: absolute;
  inset: 0;
  z-index: 1;
  background: linear-gradient(
    90deg,
    rgb(10 18 30 / 62%) 0%,
    rgb(10 18 30 / 35%) 48%,
    rgb(10 18 30 / 8%) 100%
  );
  pointer-events: none;
}

/* ========================================
   HERO CONTENT
======================================== */

.hero__inner {
  position: relative;
  z-index: 2;
}

.hero__content {
  display: grid;
  justify-items: center;
  gap: 1.25rem;
  text-align: center;
  color: var(--c-white, #fff);
}

.hero__headline {
  max-width: 850px;
  margin: 0;
  font-family: var(--ff-display, inherit);
  font-size: clamp(2rem, 5vw, 4rem);
  font-weight: 600;
  line-height: 1.12;
  letter-spacing: -0.035em;
  text-wrap: balance;
  text-shadow: 0 2px 18px rgb(0 0 0 / 18%);
}

.hero__slide-title {
  margin: 0;
  font-size: clamp(0.85rem, 1.5vw, 1.1rem);
  font-weight: 500;
  line-height: 1.7;
  letter-spacing: 0.12em;
  text-transform: uppercase;
}

.hero__cta {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 0.65rem;
  padding: 0.9rem 1.5rem;
  border-radius: 999px;
  text-decoration: none;
  transition:
    transform 200ms ease,
    box-shadow 200ms ease;
}

.hero__cta:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 24px rgb(0 0 0 / 18%);
}

/* ========================================
   SLIDER CONTROLS
======================================== */

.hero__controls {
  position: absolute;
  inset-inline: 0;
  bottom: 1.75rem;
  z-index: 5;

  display: flex;
  align-items: center;
  justify-content: center;
  gap: 1rem;

  padding-inline: 1.5rem;
  color: #fff;
}

/* Modern circular arrow and pause buttons */
.hero__arrow {
  display: inline-grid;
  flex: 0 0 auto;
  place-items: center;

  width: 46px;
  height: 46px;
  padding: 0;

  border: 1px solid rgb(255 255 255 / 55%);
  border-radius: 50%;
  background: rgb(20 28 40 / 24%);
  color: inherit;

  font-size: 1rem;
  line-height: 1;
  cursor: pointer;

  backdrop-filter: blur(10px);
  -webkit-backdrop-filter: blur(10px);

  transition:
    background-color 220ms ease,
    border-color 220ms ease,
    color 220ms ease,
    transform 220ms ease;
}

.hero__arrow:hover {
  background: var(--c-gold, #c9a45c);
  border-color: var(--c-gold, #c9a45c);
  color: #fff;
  transform: scale(1.06);
}

.hero__arrow:active {
  transform: scale(0.96);
}

.hero__arrow:focus-visible,
.hero__dot:focus-visible {
  outline: 2px solid #fff;
  outline-offset: 4px;
}

/* Pagination dots */
.hero__pagination {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.55rem;
}

.hero__dot {
  position: relative;
  display: inline-grid;
  flex: 0 0 auto;
  place-items: center;

  width: 10px;
  height: 10px;
  min-width: 10px;
  min-height: 10px;
  padding: 0;

  border: 1px solid rgb(255 255 255 / 85%);
  border-radius: 50%;
  background: transparent;
  cursor: pointer;

  transition:
    width 250ms ease,
    background-color 250ms ease,
    border-color 250ms ease,
    transform 250ms ease;
}

.hero__dot:hover {
  transform: scale(1.2);
}

.hero__dot.is-active {
  width: 28px;
  border-color: var(--c-gold, #c9a45c);
  border-radius: 999px;
  background: var(--c-gold, #c9a45c);
}

/* Slide counter */
.hero__counter {
  min-width: 58px;
  color: inherit;
  font-size: 0.78rem;
  font-weight: 600;
  font-variant-numeric: tabular-nums;
  letter-spacing: 0.12em;
  text-align: center;
  white-space: nowrap;
}

/* ========================================
   RESPONSIVE DESIGN
======================================== */

@media (max-width: 768px) {
  .hero__headline {
    font-size: clamp(1.9rem, 7vw, 3rem);
    letter-spacing: -0.025em;
  }

  .hero__controls {
    bottom: 1.25rem;
    gap: 0.65rem;
    padding-inline: 1rem;
  }

  .hero__arrow {
    width: 40px;
    height: 40px;
    font-size: 0.9rem;
  }

  .hero__pagination {
    gap: 0.4rem;
  }

  .hero__dot {
    width: 8px;
    height: 8px;
    min-width: 8px;
    min-height: 8px;
  }

  .hero__dot.is-active {
    width: 22px;
  }

  .hero__counter {
    min-width: 48px;
    font-size: 0.7rem;
  }

  .hero__scrim {
    background: linear-gradient(
      0deg,
      rgb(10 18 30 / 55%) 0%,
      rgb(10 18 30 / 18%) 65%,
      rgb(10 18 30 / 12%) 100%
    );
  }
}

@media (max-width: 380px) {
  .hero__controls {
    gap: 0.45rem;
  }

  .hero__arrow {
    width: 34px;
    height: 34px;
  }

  .hero__counter {
    min-width: 42px;
    font-size: 0.65rem;
  }

  .hero__pagination {
    gap: 0.3rem;
  }
}

/* ========================================
   ACCESSIBILITY
======================================== */

@media (prefers-reduced-motion: reduce) {
  .hero__slide,
  .hero__arrow,
  .hero__dot,
  .hero__cta {
    transition: none !important;
    animation: none !important;
  }

  .hero__slide {
    transform: none;
  }
}
</style>