import { hasIO, prefersReducedMotion } from '../utils/media.js';

/**
 * v-reveal  /  v-reveal="'image'"
 * Fades/slides an element in once it enters the viewport. Content stays visible without
 * IntersectionObserver or when the visitor prefers reduced motion (CSS keys off `.reveal-armed`).
 * The `data-reveal` attribute is what the theme CSS targets.
 */
let io;
const armed = hasIO && !prefersReducedMotion;

const getObserver = () => {
  if (!io) {
    document.documentElement.classList.add('reveal-armed');
    io = new IntersectionObserver((entries) => {
      entries.forEach((e) => {
        if (!e.isIntersecting) return;
        e.target.classList.add('is-in');
        io.unobserve(e.target);
      });
    }, { threshold: 0.15, rootMargin: '0px 0px -5% 0px' });
  }
  return io;
};

export const reveal = {
  created(el, { value }) {
    el.setAttribute('data-reveal', value === 'image' ? 'image' : '');
  },
  mounted(el) {
    if (armed) getObserver().observe(el);
  },
  unmounted(el) {
    io?.unobserve(el);
  },
};
