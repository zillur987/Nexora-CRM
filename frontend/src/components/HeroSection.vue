<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue';
import SearchBar from './SearchBar.vue';
import { useHeaderSentinel } from '../composables/useHeaderState.js';
import { asset } from '../utils/asset.js';
import { prefersReducedMotion, hasIO } from '../utils/media.js';

/**
 * Hero background video (real-estate footage).
 * - Skipped for reduced-motion and data-saver users (the CSS bokeh stays as the background).
 * - If the file is missing or fails, the bokeh fallback remains visible.
 * - Pauses while the banner is off-screen to save battery/CPU.
 * Replace public/assets/video/hero.mp4 and hero-poster.jpg, or change the paths below.
 */
const props = defineProps({
  videoSrc: { type: String, default: () => asset('video/hero.mp4') },
  poster: { type: String, default: () => asset('video/hero-poster.jpg') },
});

const video = ref(null);
const sentinel = ref(null);
useHeaderSentinel(sentinel);

let io;
onMounted(() => {
  const el = video.value;
  const saveData = navigator.connection?.saveData === true;
  if (!el || !props.videoSrc || prefersReducedMotion || saveData) return;

  el.muted = true; // required for autoplay in Safari/iOS
  if (props.poster) el.poster = props.poster;
  el.src = props.videoSrc;

  el.addEventListener('canplay', () => {
    el.classList.add('is-ready');
    el.play().catch(() => {});
  }, { once: true });
  el.addEventListener('error', () => el.classList.remove('is-ready'), { once: true });
  el.load();

  if (hasIO) {
    io = new IntersectionObserver(([entry]) => {
      if (!el.classList.contains('is-ready')) return;
      if (entry.isIntersecting) el.play().catch(() => {});
      else el.pause();
    });
    io.observe(el);
  }
});
onBeforeUnmount(() => io?.disconnect());
</script>

<template>
  <section class="hero" aria-label="Find your property" data-hero>
    <div class="hero__media" aria-hidden="true">
      <video ref="video" class="hero__video" muted loop playsinline preload="none"></video>
      <div class="hero__bokeh"></div>
      <div class="hero__scrim"></div>
    </div>

    <!-- Header turns solid once this line scrolls past the top: i.e. when the banner is finished -->
    <div ref="sentinel" class="hero__end" aria-hidden="true"></div>

    <div class="hero__inner">
      <h1 class="visually-hidden">Find your next home with Aurelia Properties</h1>
      <SearchBar />
    </div>
  </section>
</template>
