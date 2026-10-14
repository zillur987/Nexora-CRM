/** Search bar fields. `value: ''` means "Any" and is not submitted. */
export const searchFields = [
  { name: 'community', label: 'Community', wide: true, options: [
    ['', 'Any'], ['harbour-point', 'Harbour Point'], ['the-meadows', 'The Meadows'],
    ['skyline-district', 'Skyline District'], ['palm-grove', 'Palm Grove'],
  ] },
  { name: 'type', label: 'Property type', options: [
    ['', 'Any'], ['apartment', 'Apartment'], ['villa', 'Villa'], ['townhouse', 'Townhouse'], ['penthouse', 'Penthouse'],
  ] },
  { name: 'bedrooms', label: 'Bedrooms', options: [
    ['', 'Any'], ['1', '1'], ['2', '2'], ['3', '3'], ['4', '4'], ['5+', '5+'],
  ] },
  { name: 'price', label: 'Price', options: [
    ['', 'Any'], ['0-500k', 'Up to 500K'], ['500k-1m', '500K – 1M'], ['1m-3m', '1M – 3M'], ['3m+', '3M+'],
  ] },
];

export const statusChips = [
  { value: 'off-plan', label: 'Off-plan' },
  { value: 'ready', label: 'Ready' },
  { value: 'handover-2027', label: 'Handover 2027' },
];
