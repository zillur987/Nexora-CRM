import { onMounted, onBeforeUnmount } from 'vue';

/** Add a listener on mount and remove it on unmount. `target` may be an element, window, document or a getter. */
export function useEventListener(target, event, handler, options) {
  const resolve = () => (typeof target === 'function' ? target() : target);
  onMounted(() => resolve()?.addEventListener(event, handler, options));
  onBeforeUnmount(() => resolve()?.removeEventListener(event, handler, options));
}
