export const navLinks = {
  home: { label: 'Home', href: '#home' },
  about: { label: 'About', href: '#about' },
  gallery: { label: 'Gallery', href: '#gallery' },
};

/** Mega menus. `imgClass` picks the placeholder gradient; swap for a photo in mega-menu.css. */
export const megaMenus = [
  {
    id: 'services',
    label: 'Services',
    href: '#services',
    title: 'Services',
    meta: '6 services · Support 7 days a week',
    columns: [
      { heading: 'Buy & sell', links: [
        ['Property sales', 'Off-plan and ready homes'],
        ['Resale', 'Verified listings'],
        ['Valuation', 'Free market appraisal'],
      ] },
      { heading: 'Invest', links: [
        ['Investment advisory', 'Yield and growth analysis'],
        ['Mortgage support', 'Trusted lending partners'],
        ['Portfolio review', 'Annual health check'],
      ] },
      { heading: 'Ownership', links: [
        ['Property management', 'Leasing and upkeep'],
        ['Handover support', 'Snagging and move-in'],
        ['All services', 'View the full list'],
      ] },
    ],
    feature: {
      href: '#services', imgClass: 'mega__img--b', alt: 'Property management',
      label: 'Most requested', title: 'Property management', sub: 'Care for your home, start to finish',
    },
  },
  {
    id: 'projects',
    label: 'Projects',
    href: '#projects',
    title: 'Projects',
    meta: '14 new launches · 62 with units available',
    columns: [
      { heading: 'Newly launched', links: [
        ['Project One', 'Harbour Point'],
        ['Project Two', 'The Meadows'],
        ['Project Three', 'Skyline District'],
        ['All latest launches', '67 releases'],
      ] },
      { heading: 'Most availability', links: [
        ['Project Four', '24 units'],
        ['Project Five', '23 units'],
        ['Project Six', '22 units'],
        ['Project Seven', '21 units'],
      ] },
      { heading: 'By community', links: [
        ['Harbour Point', '19 releases'],
        ['The Meadows', '11 releases'],
        ['Skyline District', '11 releases'],
        ['Palm Grove', '9 releases'],
      ] },
    ],
    feature: {
      href: '#projects', imgClass: 'mega__img--a', alt: 'Project One',
      label: 'Newly launched', title: 'Project One', sub: 'Harbour Point',
    },
  },
];

export const languages = [
  { code: 'en', label: 'English', short: 'EN' },
  { code: 'ar', label: 'العربية', short: 'AR' },
  { code: 'fr', label: 'Français', short: 'FR' },
];
