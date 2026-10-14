<script setup>
import { pillars } from '../data/sections.js';
import { imgVar } from '../utils/asset.js';
import { pad } from '../utils/media.js';
</script>

<template>
  <section class="why sx sx--soft" id="why" aria-labelledby="why-title">
    <div class="sx__inner why__grid">
      <div class="why__media">
        <div class="why__frame" v-reveal="'image'">
          <span class="why__img ph" v-parallax="36" data-grad="d" :style="imgVar('img/why-aurelia.jpg')" role="img" aria-label="Aurelia residence exterior at dusk"></span>
        </div>
        <p class="why__badge" v-reveal><b>20+</b><span>years creating places people love to live</span></p>
      </div>

      <div class="why__body">
        <p class="eyebrow eyebrow--light" v-reveal>Why Aurelia</p>
        <h2 class="sx__title" id="why-title" v-reveal>Designed with intent. Built to last.</h2>
        <ol class="pillars">
          <li v-for="([title, text], i) in pillars" :key="title" class="pillar" v-reveal>
            <span class="pillar__num">{{ pad(i + 1) }}</span>
            <div><h3>{{ title }}</h3><p>{{ text }}</p></div>
          </li>
        </ol>
      </div>
    </div>
  </section>
</template>
