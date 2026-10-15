/**
 * Shown only if the API cannot be reached, so the site never renders a broken header.
 * Same shape as GET /public/header and /public/banner. Keep it short and neutral.
 */
export const fallbackHeader = {
  brand: { name: 'Jamidar Landmark', logo_url: null },
  menu: [
    { type: 'link', id: 'home', label: 'Home', url: '/', target: '_self' },
    { type: 'link', id: 'about', label: 'About', url: '#about', target: '_self' },
    { type: 'link', id: 'gallery', label: 'Gallery', url: '#gallery', target: '_self' },
  ],
  actions: { search_label: 'Search', search_url: '#search', cta_label: 'Get in touch', cta_url: '#contact', phone: null, whatsapp: null },
  languages: [],
};

export const fallbackBanner = {
  hero: { headline: 'Jamidar Landmark Limited', video_url: null, poster_url: null, show_search: true },
  slides: [],
};
