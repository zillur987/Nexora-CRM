<script setup>
import { computed, reactive } from 'vue';
import { calculatePayment } from '../utils/payment.js';

/** Instant mortgage estimate. Sliders are v-model bound; every figure is a computed value. */
const props = defineProps({ currency: { type: String, default: 'USD' } });

const locale = document.documentElement.lang || 'en-US';
const money = new Intl.NumberFormat(locale, { style: 'currency', currency: props.currency, maximumFractionDigits: 0 });

const FORMATS = {
  usd: (v) => money.format(v),
  pct: (v) => `${v}%`,
  yrs: (v) => `${v} years`,
  rate: (v) => `${Number(v).toFixed(1)}%`,
};

const fields = [
  { id: 'c-price', name: 'price', label: 'Property price', min: 150000, max: 5000000, step: 10000, fmt: 'usd' },
  { id: 'c-down', name: 'down', label: 'Down payment', min: 10, max: 60, step: 5, fmt: 'pct' },
  { id: 'c-years', name: 'years', label: 'Loan term', min: 5, max: 30, step: 1, fmt: 'yrs' },
  { id: 'c-rate', name: 'rate', label: 'Interest rate (annual)', min: 1, max: 10, step: 0.1, fmt: 'rate' },
];

const v = reactive({ price: 850000, down: 20, years: 25, rate: 4.5 });

const result = computed(() => calculatePayment({ price: v.price, downPct: v.down, years: v.years, ratePct: v.rate }));
const principalShare = computed(() => Math.round((result.value.loan / result.value.total) * 100));
const pct = (f) => `${((v[f.name] - f.min) / (f.max - f.min)) * 100}%`;
const text = (f) => FORMATS[f.fmt](v[f.name]);
</script>

<template>
  <section class="calc sx sx--paper2" id="calculator" aria-labelledby="calc-title">
    <div class="sx__inner">
      <header class="sx__head" v-reveal>
        <div>
          <p class="eyebrow eyebrow--dark">Plan your purchase</p>
          <h2 class="sx__title" id="calc-title">See what your home could cost each month</h2>
        </div>
        <p class="sx__lead">Adjust the sliders for an instant estimate. An advisor can then tailor a payment plan to your situation.</p>
      </header>

      <div class="calc__grid" v-reveal>
        <form class="calc__form" aria-label="Mortgage estimate" @submit.prevent>
          <div v-for="f in fields" :key="f.id" class="field">
            <label :for="f.id">{{ f.label }}</label>
            <output :for="f.id">{{ text(f) }}</output>
            <input
              :id="f.id"
              v-model.number="v[f.name]"
              class="range"
              :name="f.name"
              type="range"
              :min="f.min"
              :max="f.max"
              :step="f.step"
              :style="{ '--pct': pct(f) }"
              :aria-valuetext="text(f)"
            >
          </div>
        </form>

        <div class="calc__result">
          <p class="calc__label">Estimated monthly payment</p>
          <p class="calc__monthly" aria-live="polite">{{ money.format(result.monthly) }}<small> / month</small></p>
          <div
            class="calc__bar"
            :style="{ '--p': principalShare }"
            role="img"
            :aria-label="`Principal ${principalShare}%, interest ${100 - principalShare}%`"
          ></div>
          <p class="calc__legend"><span>Principal {{ principalShare }}%</span><span>Interest {{ 100 - principalShare }}%</span></p>
          <dl class="calc__rows">
            <div><dt>Down payment</dt><dd>{{ money.format(result.down) }}</dd></div>
            <div><dt>Loan amount</dt><dd>{{ money.format(result.loan) }}</dd></div>
            <div><dt>Total interest</dt><dd>{{ money.format(result.interest) }}</dd></div>
            <div><dt>Total payable</dt><dd>{{ money.format(result.total) }}</dd></div>
          </dl>
          <a v-magnetic class="btn-gold" href="#contact">Speak to an advisor <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
          <p class="calc__note">Indicative estimate only. It excludes fees, taxes and insurance and is not an offer of finance.</p>
        </div>
      </div>
    </div>
  </section>
</template>
