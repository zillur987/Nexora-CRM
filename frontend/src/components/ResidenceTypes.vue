<script setup>
import { ref } from 'vue';
import { residenceTypes } from '../data/sections.js';
import { imgVar } from '../utils/asset.js';
import { pad } from '../utils/media.js';

/** Expanding panels (desktop). On touch screens the first tap opens a panel, the second follows the link. */
const active = ref(0);

const onClick = (e, i) => {
  const touchDesktop = window.matchMedia('(min-width: 992px) and (pointer: coarse)').matches;
  if (touchDesktop && active.value !== i) {
    e.preventDefault();
    active.value = i;
  }
};
</script>

<template>
  <section class="types sx sx--paper" id="residences" aria-labelledby="types-title">
    <div class="sx__inner">
      <header class="sx__head" v-reveal>
        <div>
          <p class="eyebrow eyebrow--dark">Residences</p>
          <h2 class="sx__title" id="types-title">A home for every chapter</h2>
        </div>
        <p class="sx__lead">Whether it is a first apartment, a family townhouse or a signature penthouse, every residence is built to the same exacting standard.</p>
      </header>

      <ul class="types__row" v-reveal>
        <li
          v-for="(t, i) in residenceTypes"
          :key="t.type"
          class="type"
          :class="{ 'is-active': active === i }"
          @mouseenter="active = i"
          @focusin="active = i"
          @click="onClick($event, i)"
        >
          <a class="type__link" :href="`properties.html?type=${t.type}`">
            <span class="type__img ph" :data-grad="t.grad" :style="imgVar(`img/${t.img}`)"></span>
            <span class="type__num" aria-hidden="true">{{ pad(i + 1) }}</span>
            <span class="type__go" aria-hidden="true"><i class="bi bi-arrow-up-right"></i></span>
            <h3 class="type__title">{{ t.title }}</h3>
            <div class="type__more"><div>
              <p class="type__desc">{{ t.desc }}</p>
              <p class="type__meta"><span>{{ t.price }}</span><span>{{ t.beds }}</span></p>
            </div></div>
          </a>
        </li>
      </ul>
    </div>
  </section>
</template>
