<script setup>
import { ref } from 'vue';
import { bgUrl } from '../utils/asset.js';

defineProps({
  item: { type: Object, required: true },
  open: { type: Boolean, default: false },
});
const emit = defineEmits(['enter', 'leave', 'focus-in', 'focus-out', 'toggle']);

const root = ref(null);
const link = ref(null);

// Only report focus leaving when it moves outside this menu item.
const onFocusOut = (e) => {
  if (!root.value?.contains(e.relatedTarget)) emit('focus-out');
};

defineExpose({ focusLink: () => link.value?.focus() });
</script>

<template>
  <li
    ref="root"
    class="nav-item has-mega"
    :class="{ 'is-open': open }"
    @mouseenter="emit('enter')"
    @mouseleave="emit('leave')"
    @focusin="emit('focus-in')"
    @focusout="onFocusOut"
  >
    <a
      ref="link"
      class="nav-link"
      :href="item.url"
      :target="item.target"
      aria-haspopup="true"
      :aria-expanded="String(open)"
      :aria-controls="`mega-${item.id}`"
    >{{ item.label }}</a>
    <button
      class="mega-toggle"
      type="button"
      :aria-label="`Show ${item.label} submenu`"
      :aria-expanded="String(open)"
      :aria-controls="`mega-${item.id}`"
      @click="emit('toggle')"
    ><i class="bi bi-chevron-down" aria-hidden="true"></i></button>

    <div class="mega" :id="`mega-${item.id}`">
      <div class="mega__inner">
        <div class="mega__head">
          <span class="mega__title">{{ item.title }}</span>
          <span class="mega__rule" aria-hidden="true"></span>
          <span v-if="item.meta" class="mega__meta">{{ item.meta }}</span>
        </div>
        <div class="mega__grid">
          <div v-for="col in item.columns" :key="col.heading" class="mega__col">
            <p class="mega__heading">{{ col.heading }}</p>
            <ul>
              <li v-for="link in col.links" :key="link.label">
                <a :href="link.url" :target="link.target" :rel="link.target === '_blank' ? 'noopener' : undefined"><span class="mega__name">{{ link.label }}</span><span v-if="link.sub" class="mega__sub">{{ link.sub }}</span></a>
              </li>
            </ul>
          </div>

          <a v-if="item.feature" class="mega__feature" :href="item.feature.url">
            <!-- Photo from the CMS; the theme gradient stays as the fallback when no image is set -->
            <span class="mega__img" :class="{ 'mega__img--a': !item.feature.image_url }" :style="bgUrl(item.feature.image_url)" role="img" :aria-label="item.feature.title"></span>
            <span class="mega__feature-row"><span class="mega__feature-label">{{ item.feature.label }}</span><span class="mega__cta">Explore</span></span>
            <span class="mega__feature-title">{{ item.feature.title }}</span>
            <span class="mega__feature-sub">{{ item.feature.sub }}</span>
          </a>
        </div>
      </div>
    </div>
  </li>
</template>
