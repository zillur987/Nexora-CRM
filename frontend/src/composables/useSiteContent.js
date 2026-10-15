import { reactive } from 'vue';
import { siteApi } from '../api/client.js';
import { fallbackHeader, fallbackBanner } from '../data/fallback.js';

/**
 * Header and banner content from the CMS API.
 * - Each resource is requested once and shared by every component that asks for it.
 * - `loaded` flips to true when the request finishes (success OR failure); on failure `data` holds the fallback.
 * - Until then `data` is empty, so visitors never see placeholder content flash and change.
 */
const empty = {
  header: { brand: { name: '', logo_url: null }, menu: [], actions: {}, languages: [] },
  banner: { hero: { headline: '', video_url: null, poster_url: null, show_search: true }, slides: [] },
};
const fallbacks = { header: fallbackHeader, banner: fallbackBanner };
console.log(siteApi, 'site key')

const state = reactive({
  header: { data: empty.header, loaded: false, failed: false },
  banner: { data: empty.banner, loaded: false, failed: false },
});
const requests = {};

function load(key) {
  requests[key] ??= siteApi[key]()
    .then((data) => { state[key].data = data; })
    .catch(() => { state[key].data = fallbacks[key]; state[key].failed = true; })
    .finally(() => { state[key].loaded = true; });
  return state[key];
}

export const useHeaderContent = () => load('header');
export const useBannerContent = () => load('banner');
