import { ref, onMounted, onBeforeUnmount } from 'vue';
import { hasIO } from '../utils/media.js';

/** Reactive "is this element intersecting?" for a template ref. */
export function useInView(elRef, options = { threshold: 0.25 }) {
  const inView = ref(true);
  let io;
  onMounted(() => {
    if (!hasIO || !elRef.value) return;
    io = new IntersectionObserver(([entry]) => { inView.value = entry.isIntersecting; }, options);
    io.observe(elRef.value);
  });
  onBeforeUnmount(() => io?.disconnect());
  return inView;
}
