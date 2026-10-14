<script setup>
import { ref, computed } from 'vue';
import { communities } from '../data/sections.js';
import { imgVar } from '../utils/asset.js';
import { pad } from '../utils/media.js';

/** Hover / focus a row to change the stage. */
const active = ref(0);
const current = computed(() => communities[active.value]);
</script>

<template>
  <section class="communities sx sx--ink" id="communities" aria-labelledby="communities-title">
    <div class="sx__inner">
      <header class="sx__head" v-reveal>
        <div>
          <p class="eyebrow eyebrow--light">Communities</p>
          <h2 class="sx__title" id="communities-title">Find the neighbourhood that fits your life</h2>
        </div>
        <p class="sx__lead">From the waterfront to the hills, each community is master-planned around green space, schools, retail and effortless connections to the city.</p>
      </header>

      <div class="comm">
        <ul class="comm__list">
          <li v-for="(c, i) in communities" :key="c.slug">
            <a
              class="comm__item"
              :class="{ 'is-active': active === i }"
              :href="`#community-${c.slug}`"
              @mouseenter="active = i"
              @focus="active = i"
            >
              <span class="comm__num">{{ pad(i + 1) }}</span>
              <span><span class="comm__name">{{ c.name }}</span><span class="comm__meta">{{ c.meta }}</span></span>
              <i class="bi bi-arrow-up-right" aria-hidden="true"></i>
            </a>
          </li>
        </ul>

        <!-- Photos: public/assets/img/community-*.jpg (about 1400x1480) -->
        <div class="comm__stage" v-reveal="'image'">
          <span
            v-for="(c, i) in communities"
            :key="c.slug"
            class="comm__pane ph"
            :class="{ 'is-active': active === i }"
            :data-grad="c.grad"
            :style="imgVar(`img/${c.img}`)"
          ></span>
          <div class="comm__caption">
            <p class="comm__desc">{{ current.desc }}</p>
            <p class="comm__price"><small>From</small><span>{{ current.price }}</span></p>
          </div>
        </div>
      </div>
    </div>
  </section>
</template>
