import { hasIO, prefersReducedMotion } from '../utils/media.js';

/**
 * v-parallax="36"  — shifts the element against scroll by up to 36px (--py).
 * Only elements near the viewport are updated; a single scroll listener serves all of them.
 */
const visible = new Set();
let io;
let listening = false;
let ticking = false;

const update = () => {
  ticking = false;
  const vh = window.innerHeight;
  visible.forEach((el) => {
    const host = el.parentElement.getBoundingClientRect();
    const p = Math.max(-1, Math.min(1, (host.top + host.height / 2 - vh / 2) / (vh / 2 + host.height / 2)));
    el.style.setProperty('--py', `${(p * -(Number(el.dataset.parallax) || 40)).toFixed(1)}px`);
  });
};
const onScroll = () => { if (!ticking) { ticking = true; requestAnimationFrame(update); } };

export const parallax = {
  created(el, { value }) {
    el.setAttribute('data-parallax', value ?? 40);
  },
  mounted(el) {
    if (prefersReducedMotion || !hasIO) return;
    io ??= new IntersectionObserver((entries) => {
      entries.forEach((e) => (e.isIntersecting ? visible.add(e.target) : visible.delete(e.target)));
      update();
    }, { rootMargin: '15% 0px' });
    io.observe(el);
    if (!listening) { listening = true; window.addEventListener('scroll', onScroll, { passive: true }); }
  },
  unmounted(el) {
    io?.unobserve(el);
    visible.delete(el);
  },
};
