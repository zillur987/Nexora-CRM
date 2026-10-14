<script setup>
import { journeySteps } from '../data/sections.js';
import { pad } from '../utils/media.js';
</script>

<template>
  <section class="journey sx sx--ink" id="journey" aria-labelledby="journey-title">
    <div class="sx__inner">
      <header class="sx__head" v-reveal>
        <div>
          <p class="eyebrow eyebrow--light">The journey</p>
          <h2 class="sx__title" id="journey-title">From first visit to the keys in your hand</h2>
        </div>
        <p class="sx__lead">A clear, guided process with one point of contact at every step, whether you buy locally or from overseas.</p>
      </header>

      <ol class="steps">
        <li v-for="([title, text, time], i) in journeySteps" :key="title" class="step" :style="{ '--i': i }" v-reveal>
          <span class="step__num" aria-hidden="true">{{ pad(i + 1) }}</span>
          <h3>{{ title }}</h3>
          <p>{{ text }}</p>
          <span class="step__time">{{ time }}</span>
        </li>
      </ol>

      <div class="journey__cta" v-reveal>
        <a v-magnetic class="btn-gold" href="#contact">Book a private consultation <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
        <a class="link-arrow" href="#projects">Explore residences <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
      </div>
    </div>
  </section>
</template>
