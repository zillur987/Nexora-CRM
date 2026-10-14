<script setup>
import { marqueeItems } from '../data/sections.js';
</script>

<template>
  <div class="marquee" id="marquee" role="group" aria-label="Property collections">
    <div class="marquee__track">
      <!-- The list is rendered twice for a seamless loop; the copy is hidden from assistive tech. -->
      <ul v-for="copy in 2" :key="copy" class="marquee__list" :aria-hidden="copy === 2 ? 'true' : undefined">
        <li v-for="item in marqueeItems" :key="item">
          <span>{{ item }}</span>
          <svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12 0l2.6 9.4L24 12l-9.4 2.6L12 24l-2.6-9.4L0 12l9.4-2.6z"/></svg>
        </li>
      </ul>
    </div>
  </div>
</template>
