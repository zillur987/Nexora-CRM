export const projectFilters = [
  { value: 'all', label: 'All' },
  { value: 'off-plan', label: 'Off-plan' },
  { value: 'ready', label: 'Ready' },
  { value: 'handover-2027', label: 'Handover 2027' },
];

export const projects = [
  { slug: 'harbour-point', status: 'off-plan', badge: 'Off-plan', grad: 'a', img: 'harbour-point.jpg',
    type: 'Waterfront apartments', title: 'Harbour Point', price: '$420K',
    meta: ['1–3 Beds', '68–190 m²', 'Q4 2028'] },
  { slug: 'the-meadows', status: 'ready', badge: 'Ready', grad: 'b', img: 'the-meadows.jpg',
    type: 'Garden townhouses', title: 'The Meadows', price: '$890K',
    meta: ['3–4 Beds', '210–280 m²', 'Move in now'] },
  { slug: 'skyline-district', status: 'off-plan', badge: 'Off-plan', grad: 'c', img: 'skyline-district.jpg',
    type: 'Sky penthouses', title: 'Skyline District', price: '$2.4M',
    meta: ['3–5 Beds', '260–620 m²', 'Q2 2029'] },
  { slug: 'palm-grove', status: 'handover-2027', badge: 'Handover 2027', grad: 'd', img: 'palm-grove.jpg',
    type: 'Private villas', title: 'Palm Grove', price: '$3.1M',
    meta: ['4–6 Beds', '380–720 m²', 'Q3 2027'] },
  { slug: 'marina-walk', status: 'ready', badge: 'Ready', grad: 'e', img: 'marina-walk.jpg',
    type: 'Marina apartments', title: 'Marina Walk', price: '$310K',
    meta: ['Studio–2 Beds', '42–120 m²', 'Move in now'] },
  { slug: 'cedar-hills', status: 'handover-2027', badge: 'Handover 2027', grad: 'f', img: 'cedar-hills.jpg',
    type: 'Hillside townhouses', title: 'Cedar Hills', price: '$760K',
    meta: ['3–4 Beds', '190–260 m²', 'Q1 2027'] },
];

export const figures = [
  { label: 'Years of experience', value: 18, suffix: '+' },
  { label: 'Homes delivered', value: 12400, suffix: '+', group: true },
  { label: 'Master-planned communities', value: 14 },
  { label: 'Client satisfaction', value: 97, suffix: '%' },
];
