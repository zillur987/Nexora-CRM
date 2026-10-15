const BASE = (import.meta.env.VITE_API_BASE_URL || '/api/v1').replace(/\/$/, '');
console.log(BASE, 'base')
const TIMEOUT_MS = 8000;

/** GET JSON from the public CMS API. Rejects on network errors, timeouts and non-2xx responses. */
export async function getJson(path) {
  const controller = new AbortController();
  const timer = setTimeout(() => controller.abort(), TIMEOUT_MS);
  try {
    const res = await fetch(`${BASE}${path}`, { headers: { Accept: 'application/json' }, signal: controller.signal });
    if (!res.ok) throw new Error(`Request failed (${res.status})`);
    return (await res.json()).data;
  } finally {
    clearTimeout(timer);
  }
}
console.log(getJson('/public/header'), 'header')
export const siteApi = {
  header: () => getJson('/public/header'),
  banner: () => getJson('/public/banner'),
};
