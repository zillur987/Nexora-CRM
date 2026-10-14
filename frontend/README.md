# Aurelia Theme: Vue 3 + Vite

The Aurelia Properties theme, ported from vanilla HTML/JS to **Vue 3.5** (Composition API, `<script setup>`)
built with **Vite 8**. Bootstrap 5.3 and Bootstrap Icons are installed from npm. The CSS is unchanged, so the
design is identical to v4.

## Run
Requires Node 20.19+ (or 22.12+).

    npm install
    npm run dev        # http://localhost:5173
    npm run build      # output in dist/ (relative paths: host it in any folder)
    npm run preview

## Structure
    index.html               fonts + mount point
    public/assets/           img/ and video/ (served as-is)
    src/
      main.js                app bootstrap, Bootstrap CSS/JS, theme CSS, directives
      App.vue                page composition
      styles/                tokens.css, base.css, header.css ... (same files as v4)
      components/            one component per section (AppHeader, HeroSection, SearchBar, ProjectsShowcase,
                             SpotlightSlider, ResidenceTypes, CommunitiesSection, GalleryCarousel, WhySection,
                             PaymentCalculator, JourneySection, InsightsSection, ClientStories, SiteFooter ...)
      composables/           useEventListener, useMediaQuery, useInView, useRafThrottle, useHeaderState
      directives/            v-reveal, v-parallax, v-magnetic
      data/                  all copy and lists (nav, projects, spotlight, sections, footer ...)
      utils/                 asset URLs, media/motion checks, payment maths

## What changed from v4
| v4 (vanilla)                                | v5 (Vue)                                                 |
|---------------------------------------------|----------------------------------------------------------|
| `data-*` hooks + `querySelector`            | refs, reactive state, `v-for` over `src/data/*`          |
| `initX()` modules in `main.js`              | one component per section, composables for shared logic  |
| `[data-reveal]` / `data-parallax`           | `v-reveal`, `v-parallax="36"`, `v-magnetic` directives   |
| `SearchDropdown` class                      | `SearchDropdown.vue` (`v-model`)                         |
| Bootstrap from CDN                          | npm; only Offcanvas, Collapse and Dropdown JS are bundled |
| `calculatePayment` in sections.js           | `src/utils/payment.js` (pure function) + computed values |
| Newsletter `newsletter:subscribe` DOM event | `@subscribe` event on `<SiteFooter>`                     |

Accessibility, keyboard support, reduced-motion handling, RTL support and the CSS class contract are preserved.

## Customising
- Brand, colours, spacing, fonts: `src/styles/tokens.css`.
- Copy and lists: `src/data/*.js`.
- Images: replace files in `public/assets/img/` (same names), or change the paths in `src/data`.
- Hero video: `public/assets/video/hero.mp4` + `hero-poster.jpg`, or pass `video-src` / `poster` props to `<HeroSection>`.
- Autoplay timing: props on `<ProjectsShowcase :autoplay>`, `<SpotlightSlider :interval>`, `<ClientStories :interval>`.
- Newsletter backend: `<SiteFooter newsletter-action="https://..." @subscribe="...">`.
- Search form submits GET to `properties.html`; change `action` in `SearchBar.vue`.
- RTL: set `dir="rtl"` on `<html>`.

## Notes
Brand name and demo copy are placeholders. Use your own logo and imagery.
