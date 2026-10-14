import { ref, onMounted, onBeforeUnmount } from 'vue';
import { hasIO } from '../utils/media.js';

/**
 * Header turns solid once the banner has scrolled out of view.
 * The hero registers its bottom "sentinel" element; AppHeader reads `headerScrolled`.
 * Without a sentinel it falls back to a 40px scroll threshold.
 */
export const headerScrolled = ref(false);

export function useHeaderSentinel(sentinelRef) {
  let io;
  let onScroll;
  onMounted(() => {
    const el = sentinelRef.value;
    if (el && hasIO) {
      io = new IntersectionObserver(
        ([entry]) => { headerScrolled.value = !entry.isIntersecting && entry.boundingClientRect.top < 0; },
        { threshold: 0 },
      );
      io.observe(el);
    } else {
      onScroll = () => { headerScrolled.value = window.scrollY > 40; };
      window.addEventListener('scroll', onScroll, { passive: true });
      onScroll();
    }
  });
  onBeforeUnmount(() => {
    io?.disconnect();
    if (onScroll) window.removeEventListener('scroll', onScroll);
  });
}
