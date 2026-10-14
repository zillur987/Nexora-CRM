/** Returns a function that runs `fn` at most once per animation frame. */
export function useRafThrottle(fn) {
  let ticking = false;
  return () => {
    if (ticking) return;
    ticking = true;
    requestAnimationFrame(() => { ticking = false; fn(); });
  };
}
