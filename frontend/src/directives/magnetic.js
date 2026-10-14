import { hasFinePointer, prefersReducedMotion } from '../utils/media.js';

/** v-magnetic — buttons lean slightly toward a fine pointer (desktop only, not for reduced motion). */
const handlers = new WeakMap();

export const magnetic = {
  mounted(el) {
    if (!hasFinePointer || prefersReducedMotion) return;
    const move = (e) => {
      const r = el.getBoundingClientRect();
      const x = (e.clientX - (r.left + r.width / 2)) * 0.18;
      const y = (e.clientY - (r.top + r.height / 2)) * 0.28;
      el.style.transform = `translate(${x.toFixed(1)}px, ${y.toFixed(1)}px)`;
    };
    const leave = () => { el.style.transform = ''; };
    el.addEventListener('pointermove', move);
    el.addEventListener('pointerleave', leave);
    el.style.transition = 'transform .35s cubic-bezier(.22,.61,.36,1), background-color .35s';
    handlers.set(el, { move, leave });
  },
  unmounted(el) {
    const h = handlers.get(el);
    if (!h) return;
    el.removeEventListener('pointermove', h.move);
    el.removeEventListener('pointerleave', h.leave);
  },
};
