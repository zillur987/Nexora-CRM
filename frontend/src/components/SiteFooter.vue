<script setup>
import { ref } from 'vue';
import { footerColumns, socialLinks, legalLinks } from '../data/footer.js';
import { prefersReducedMotion as reduce } from '../utils/media.js';

/**
 * Footer: current year, back-to-top and newsletter validation.
 *
 * Newsletter: with the placeholder action="#" the form is handled here (validation + a thank-you
 * message) and emits `subscribe` with the email. Pass a real `action` URL and the form submits
 * natively once the email is valid.
 */
const props = defineProps({ newsletterAction: { type: String, default: '#' } });
const emit = defineEmits(['subscribe']);

const year = new Date().getFullYear();
const email = ref('');
const company = ref(''); // honeypot: real visitors never see or fill this
const message = ref({ text: '', kind: '' });
const input = ref(null);

const toTop = () => {
  window.scrollTo({ top: 0, behavior: reduce ? 'auto' : 'smooth' });
  document.querySelector('.skip-link')?.focus({ preventScroll: true });
};

const onSubmit = (e) => {
  const value = email.value.trim();
  const valid = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(value);

  if (!valid) {
    e.preventDefault();
    input.value.setAttribute('aria-invalid', 'true');
    message.value = { text: 'Please enter a valid email address.', kind: 'error' };
    input.value.focus();
    return;
  }
  input.value.removeAttribute('aria-invalid');
  if (company.value) { e.preventDefault(); return; } // honeypot filled: silently drop

  if (!props.newsletterAction || props.newsletterAction === '#') {
    e.preventDefault();
    emit('subscribe', { email: value });
    message.value = { text: 'Thank you. You are on the list.', kind: 'ok' };
    email.value = '';
  }
};

const onInput = () => { if (message.value.kind === 'error') message.value = { text: '', kind: '' }; };
</script>

<template>
  <footer class="site-footer" id="contact">

    <!-- Call to action -->
    <div class="footer-cta">
      <div class="footer-cta__inner">
        <div>
          <p class="eyebrow eyebrow--gold">Start your search</p>
          <h2 class="footer-cta__title">Let&rsquo;s find the home that fits your life</h2>
        </div>
        <div class="footer-cta__actions">
          <a v-magnetic class="btn-gold" href="#viewing">Book a private viewing <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
          <a class="footer-cta__link" href="tel:+0000000000"><i class="bi bi-telephone" aria-hidden="true"></i> +00 000 000 000</a>
          <a class="footer-cta__link" href="mailto:hello@aurelia.example"><i class="bi bi-envelope" aria-hidden="true"></i> hello@aurelia.example</a>
        </div>
      </div>
    </div>

    <!-- Main -->
    <div class="footer-main">
      <div class="footer-brand">
        <a class="brand__mark footer-brand__mark" href="/" aria-label="Aurelia Properties home">AURELIA</a>
        <p class="footer-brand__text">Thoughtfully designed residences and communities, delivered with care from first viewing to final handover.</p>

        <form class="newsletter" :action="newsletterAction" method="post" novalidate @submit="onSubmit">
          <label class="newsletter__label" for="nl-email">Launch alerts &amp; market notes</label>
          <div class="newsletter__row">
            <input
              id="nl-email"
              ref="input"
              v-model="email"
              class="newsletter__input"
              name="email"
              type="email"
              inputmode="email"
              autocomplete="email"
              placeholder="Your email address"
              required
              aria-describedby="nl-msg"
              @input="onInput"
            >
            <input v-model="company" class="newsletter__hp" type="text" name="company" tabindex="-1" autocomplete="off" aria-hidden="true">
            <button class="newsletter__btn" type="submit" aria-label="Subscribe"><i class="bi bi-arrow-right" aria-hidden="true"></i></button>
          </div>
          <p
            class="newsletter__msg"
            :class="{ 'is-error': message.kind === 'error', 'is-ok': message.kind === 'ok' }"
            id="nl-msg"
            role="status"
            aria-live="polite"
          >{{ message.text }}</p>
          <p class="newsletter__fine">By subscribing you agree to our <a href="#privacy">Privacy Policy</a>. Unsubscribe anytime.</p>
        </form>
      </div>

      <nav v-for="col in footerColumns" :key="col.title" class="footer-col" :aria-label="col.title">
        <h3 class="footer-col__title">{{ col.title }}</h3>
        <ul>
          <li v-for="[label, href] in col.links" :key="label"><a :href="href">{{ label }}</a></li>
        </ul>
      </nav>

      <div class="footer-col footer-contact">
        <h3 class="footer-col__title">Visit us</h3>
        <address>
          <strong>Head office</strong>
          Tower One, Level 24<br>
          Harbour Boulevard, City 00000
        </address>
        <address>
          <strong>Sales gallery</strong>
          Skyline District<br>
          Open daily, 9:00 &ndash; 20:00
        </address>
        <ul class="footer-social" aria-label="Social media">
          <li v-for="s in socialLinks" :key="s.label">
            <a :href="s.href" :aria-label="s.label" target="_blank" rel="noopener"><i class="bi" :class="s.icon" aria-hidden="true"></i></a>
          </li>
        </ul>
      </div>
    </div>

    <!-- Legal -->
    <div class="footer-legal">
      <p class="footer-legal__copy">&copy; <span>{{ year }}</span> Aurelia Properties. All rights reserved.</p>
      <ul class="footer-legal__links">
        <li v-for="[label, href] in legalLinks" :key="label"><a :href="href">{{ label }}</a></li>
      </ul>
      <p class="footer-legal__licence">Licensed real estate developer &middot; Reg. No. 000000</p>
      <button class="to-top" type="button" aria-label="Back to top" @click="toTop"><i class="bi bi-arrow-up" aria-hidden="true"></i></button>
    </div>

    <!-- Oversized wordmark (decorative) -->
    <div class="footer-mark" aria-hidden="true">AURELIA</div>
  </footer>
</template>
