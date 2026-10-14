<script setup>
import { ref, nextTick } from 'vue';
import { stories, assurances } from '../data/sections.js';
import { prefersReducedMotion as reduce, scrollBehavior, pad } from '../utils/media.js';
import { useInView } from '../composables/useInView.js';

/**
 * Client stories: accessible tabs (arrow keys / Home / End) with optional autoplay.
 *
 * Autoplay is driven by the CSS progress bar on the active tab: when its animation ends we advance.
 * It pauses on hover/focus, while off-screen, and via the pause button, and is disabled entirely for
 * visitors who prefer reduced motion (they just use the tabs).
 */
const props = defineProps({ interval: { type: Number, default: 8000 } });

const root = ref(null);
const list = ref(null);
const tabEls = ref([]);
const index = ref(0);
const hover = ref(false);
const paused = ref(false);
const inView = useInView(root, { threshold: 0.25 });

const select = async (i, { focus = false } = {}) => {
  index.value = (i + stories.length) % stories.length;
  await nextTick();
  const tab = tabEls.value[index.value];
  if (focus) tab.focus();
  // keep the active tab in view inside the horizontal strip (mobile)
  if (list.value.scrollWidth > list.value.clientWidth) {
    tab.scrollIntoView({ block: 'nearest', inline: 'nearest', behavior: scrollBehavior });
  }
};

const onTabKey = (e) => {
  const map = { ArrowRight: 1, ArrowDown: 1, ArrowLeft: -1, ArrowUp: -1 };
  if (e.key in map) { e.preventDefault(); select(index.value + map[e.key], { focus: true }); }
  else if (e.key === 'Home') { e.preventDefault(); select(0, { focus: true }); }
  else if (e.key === 'End') { e.preventDefault(); select(stories.length - 1, { focus: true }); }
};

const onAnimationEnd = (e) => { if (e.animationName === 'story-fill') select(index.value + 1); };
const onPointerEnter = (e) => { if (e.pointerType === 'mouse') hover.value = true; };
</script>

<template>
  <section
    ref="root"
    class="stories"
    :class="{ 'is-auto': !reduce, 'is-hover': hover, 'is-paused': paused, 'is-offscreen': !inView }"
    :style="{ '--autoplay': `${interval / 1000}s` }"
    id="stories"
    aria-labelledby="stories-title"
    @animationend="onAnimationEnd"
  >
    <div class="stories__inner">

      <div class="stories__intro" v-reveal>
        <p class="eyebrow eyebrow--dark">Client stories</p>
        <h2 class="stories__title" id="stories-title">Trusted by families and investors worldwide</h2>
        <p class="stories__rating"><b>4.9</b><span>/ 5 average from 2,300+ verified reviews</span></p>

        <div
          ref="list"
          class="story-tabs"
          role="tablist"
          aria-label="Client stories"
          aria-orientation="vertical"
          @keydown="onTabKey"
          @pointerenter="onPointerEnter"
          @pointerleave="hover = false"
          @focusin="hover = true"
          @focusout="hover = false"
        >
          <button
            v-for="(s, i) in stories"
            :id="`story-tab-${i}`"
            :key="s.name"
            :ref="(el) => (tabEls[i] = el)"
            class="story-tab"
            role="tab"
            type="button"
            :aria-selected="String(i === index)"
            :aria-controls="`story-panel-${i}`"
            :tabindex="i === index ? 0 : -1"
            @click="select(i)"
          >
            <span class="story-tab__num" aria-hidden="true">{{ pad(i + 1) }}</span>
            <span class="story-tab__text"><span class="story-tab__name">{{ s.name }}</span><span class="story-tab__meta">{{ s.place }} &middot; {{ s.bought.split(' · ')[0] }}</span></span>
            <span class="story-tab__bar" aria-hidden="true"></span>
          </button>
        </div>
      </div>

      <div
        class="stories__stage"
        v-reveal
        @pointerenter="onPointerEnter"
        @pointerleave="hover = false"
        @focusin="hover = true"
        @focusout="hover = false"
      >
        <span class="stories__mark" aria-hidden="true">&ldquo;</span>
        <figure
          v-for="(s, i) in stories"
          :id="`story-panel-${i}`"
          :key="s.name"
          class="story"
          :class="{ 'is-active': i === index }"
          role="tabpanel"
          :aria-labelledby="`story-tab-${i}`"
          :inert="i !== index"
        >
          <div class="story__stars" role="img" aria-label="Rated 5 out of 5"><i v-for="n in 5" :key="n" class="bi bi-star-fill" aria-hidden="true"></i></div>
          <blockquote class="story__quote"><p>{{ s.quote }}</p></blockquote>
          <figcaption class="story__by">
            <span class="story__avatar" aria-hidden="true">{{ s.initials }}</span>
            <span class="story__who"><strong>{{ s.name }}</strong><span>{{ s.place }}</span></span>
            <span class="story__bought"><small>Purchased</small>{{ s.bought }}</span>
          </figcaption>
        </figure>

        <button
          class="stories__toggle"
          type="button"
          :aria-pressed="String(paused)"
          :aria-label="paused ? 'Resume automatic rotation' : 'Pause automatic rotation'"
          @click="paused = !paused"
        ><i class="bi" :class="paused ? 'bi-play-fill' : 'bi-pause-fill'" aria-hidden="true"></i></button>
      </div>
    </div>

    <!-- Awards & assurances -->
    <ul class="assurances" aria-label="Awards and assurances" v-reveal>
      <li v-for="a in assurances" :key="a.title">
        <i class="bi" :class="a.icon" aria-hidden="true"></i>
        <span><strong>{{ a.title }}</strong>{{ a.text }}</span>
      </li>
    </ul>
  </section>
</template>
