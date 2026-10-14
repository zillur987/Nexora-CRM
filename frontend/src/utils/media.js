/** Shared, evaluated-once environment checks (same semantics as the original modules). */
const mq = (q) => typeof window !== 'undefined' && window.matchMedia(q).matches;

export const prefersReducedMotion = mq('(prefers-reduced-motion: reduce)');
export const hasFinePointer = mq('(hover: hover) and (pointer: fine)');
export const hasIO = typeof window !== 'undefined' && 'IntersectionObserver' in window;
export const scrollBehavior = prefersReducedMotion ? 'auto' : 'smooth';
export const pad = (n) => String(n).padStart(2, '0');
