export const residenceTypes = [
  { type: 'apartment', grad: 'a', img: 'type-apartments.jpg', title: 'Apartments',
    desc: 'Light-filled studios to three-bedroom homes with resort-style amenities.', price: 'From $310K', beds: 'Studio – 3 Beds' },
  { type: 'townhouse', grad: 'b', img: 'type-townhouses.jpg', title: 'Townhouses',
    desc: 'Three levels of family space, private gardens and rooftop terraces.', price: 'From $890K', beds: '3 – 4 Beds' },
  { type: 'villa', grad: 'd', img: 'type-villas.jpg', title: 'Villas',
    desc: 'Private plots with pools, landscaped grounds and generous entertaining space.', price: 'From $1.9M', beds: '4 – 6 Beds' },
  { type: 'penthouse', grad: 'c', img: 'type-penthouses.jpg', title: 'Penthouses',
    desc: 'Full-floor sky residences with double-height living and 360° views.', price: 'From $3.4M', beds: '3 – 5 Beds' },
];

export const marqueeItems = ['Waterfront living', 'Private villas', 'Garden townhouses', 'Sky penthouses', 'Hillside retreats'];

/** Photos: public/assets/img/community-*.jpg (about 1400x1480) */
export const communities = [
  { slug: 'marina-bay', name: 'Marina Bay', meta: 'Waterfront · 1,240 residences', grad: 'a', img: 'community-marina-bay.jpg',
    desc: 'Promenades, marinas and apartments with open water views.', price: '$420K' },
  { slug: 'palm-grove', name: 'Palm Grove', meta: 'Private villas · 380 residences', grad: 'b', img: 'community-palm-grove.jpg',
    desc: 'Low-density private villas set within mature tropical landscape.', price: '$1.9M' },
  { slug: 'the-meadows', name: 'The Meadows', meta: 'Family living · 920 residences', grad: 'e', img: 'community-the-meadows.jpg',
    desc: 'Garden townhouses on car-light streets, minutes from the school campus.', price: '$890K' },
  { slug: 'skyline-district', name: 'Skyline District', meta: 'Urban high-rise · 2,100 residences', grad: 'c', img: 'community-skyline-district.jpg',
    desc: 'Design-led towers above a walkable district of dining and culture.', price: '$310K' },
  { slug: 'cedar-ridge', name: 'Cedar Ridge', meta: 'Hillside retreats · 260 residences', grad: 'd', img: 'community-cedar-ridge.jpg',
    desc: 'Hillside retreats with panoramic views and direct trail access.', price: '$1.2M' },
];

/** PLACEHOLDER CONTENT: captions and images (public/assets/img/gal-1..6.jpg) are demo content. */
export const galleryItems = [
  { grad: 'a', img: 'gal-1.jpg', title: 'Infinity pool terrace', place: 'Palm Grove' },
  { grad: 'c', img: 'gal-2.jpg', title: 'Skyline lounge', place: 'Skyline District', mod: 'wide' },
  { grad: 'b', img: 'gal-3.jpg', title: 'Garden courtyard', place: 'The Meadows', mod: 'sq' },
  { grad: 'e', img: 'gal-4.jpg', title: 'Marina promenade', place: 'Marina Bay' },
  { grad: 'd', img: 'gal-5.jpg', title: 'Ridge trail at dusk', place: 'Cedar Ridge', mod: 'wide' },
  { grad: 'f', img: 'gal-6.jpg', title: 'Evening living room', place: 'Palm Grove', mod: 'sq' },
];

/** PLACEHOLDER CONTENT: replace the years and claims with verified facts about the client. */
export const pillars = [
  ['Architecture with a point of view', 'Award-winning studios shape every masterplan around light, landscape and the way families actually live.'],
  ['Craftsmanship you can verify', 'Premium materials, independent inspections and a 10-year structural warranty on every residence.'],
  ['Locations that hold their value', 'Sites chosen for connectivity, infrastructure and long-term demand, backed by transparent market data.'],
  ['Care long after handover', 'A dedicated client team, property management and resale support for as long as you own your home.'],
];

export const journeySteps = [
  ['Discover', 'Share your goals and budget. We shortlist residences and arrange private or virtual tours.', 'Day 1'],
  ['Reserve', 'Choose your home and secure it with a fully documented reservation.', 'Within a week'],
  ['Finance', 'Select a payment plan or mortgage with guidance from our legal and finance partners.', '2–4 weeks'],
  ['Move in', 'Final inspection, handover and ongoing support from our after-sales team.', 'At handover'],
];

/** PLACEHOLDER CONTENT: connect to your CMS / blog API; replace titles, dates and links. */
export const posts = [
  { lead: true, grad: 'e', img: 'post-1.jpg', tag: 'Market', iso: '2026-10-02', date: '2 Oct 2026', read: '6 min read',
    title: 'Where international buyers are looking next: five emerging waterfront markets' },
  { grad: 'b', img: 'post-2.jpg', tag: 'Guide', iso: '2026-09-18', date: '18 Sep 2026', read: '5 min read',
    title: "A first-time buyer's checklist for off-plan purchases" },
  { grad: 'd', img: 'post-3.jpg', tag: 'Design', iso: '2026-09-04', date: '4 Sep 2026', read: '4 min read',
    title: 'Inside the design of a garden townhouse' },
];

/** PLACEHOLDER CONTENT: replace names, quotes, rating and awards with real, verifiable client feedback before launch. */
export const stories = [
  { name: 'Sarah & James M.', initials: 'SM', place: 'London, UK', bought: 'Harbour Point · 2-bed apartment',
    quote: 'From first call to handover, one team looked after everything. We bought off-plan from another continent and never once felt left in the dark. The finished home is exactly what the renders promised.' },
  { name: 'Rahul K.', initials: 'RK', place: 'Singapore', bought: 'Skyline District · Penthouse',
    quote: 'As an investor I care about numbers and delivery. The project landed on schedule, the specification matched the brochure, and rental demand has been stronger than the projection I was given.' },
  { name: 'Amira H.', initials: 'AH', place: 'Dubai, UAE', bought: 'The Meadows · 4-bed townhouse',
    quote: 'We wanted space and a real neighbourhood for the children. The community delivers: parks, schools within walking distance, and neighbours who became friends within a month.' },
  { name: 'Daniel & Elena P.', initials: 'DP', place: 'Berlin, Germany', bought: 'Palm Grove · Private villa',
    quote: 'The design team listened to how we actually live, then improved on it. After-sales support has been just as thoughtful. Small things get fixed fast, without chasing.' },
];

export const assurances = [
  { icon: 'bi-award', title: 'Developer of the Year', text: 'Industry awards, 2025' },
  { icon: 'bi-shield-check', title: '10-year structural warranty', text: 'On every residence' },
  { icon: 'bi-patch-check', title: 'Quality certified', text: 'ISO 9001 management' },
  { icon: 'bi-tree', title: 'Green-rated communities', text: 'Sustainable by design' },
];
