<script setup>
import { posts } from '../data/sections.js';
import { imgVar } from '../utils/asset.js';
</script>

<template>
  <section class="insights sx sx--paper" id="insights" aria-labelledby="insights-title">
    <div class="sx__inner">
      <header class="sx__head" v-reveal>
        <div>
          <p class="eyebrow eyebrow--dark">Insights</p>
          <h2 class="sx__title" id="insights-title">Ideas, guides and market notes</h2>
        </div>
        <div><a class="link-arrow" href="#">All insights <i class="bi bi-arrow-right" aria-hidden="true"></i></a></div>
      </header>

      <div class="insights__grid">
        <article v-for="p in posts" :key="p.img" class="post" :class="p.lead ? 'post--lead' : 'post--side'" v-reveal>
          <a href="#">
            <span class="post__media"><span class="post__img ph" :data-grad="p.grad" :style="imgVar(`img/${p.img}`)"></span></span>
            <div>
              <p class="post__meta"><span>{{ p.tag }}</span><time :datetime="p.iso">{{ p.date }}</time><span>{{ p.read }}</span></p>
              <h3 class="post__title">{{ p.title }}</h3>
            </div>
          </a>
        </article>
      </div>
    </div>
  </section>
</template>
