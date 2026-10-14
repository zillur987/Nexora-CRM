<script setup>
import { computed, reactive, ref, watch, onBeforeUnmount } from 'vue';
import MegaItem from './MegaItem.vue';
import { megaMenus, navLinks, languages } from '../data/nav.js';
import { headerScrolled } from '../composables/useHeaderState.js';
import { useMediaQuery } from '../composables/useMediaQuery.js';
import { useEventListener } from '../composables/useEventListener.js';

/**
 * Desktop (>=1200px): mega menus open on hover / keyboard focus and close after a short delay
 * (so the pointer can travel into the panel) or on Escape. While open the header gets `.is-mega-open`.
 * Mobile: the chevron button toggles an accordion inside the offcanvas (Bootstrap handles the offcanvas).
 */
const CLOSE_DELAY = 160;
const desktop = useMediaQuery('(min-width: 1200px)');
const openIds = reactive(new Set());
const items = ref({});
const timers = {};

const megaOpen = computed(() => desktop.value && openIds.size > 0);

const setOpen = (id, open) => (open ? openIds.add(id) : openIds.delete(id));
const closeOthers = (keep) => megaMenus.forEach(({ id }) => {
  if (id !== keep) { clearTimeout(timers[id]); setOpen(id, false); }
});

const onEnter = (id) => {
  if (!desktop.value) return;
  clearTimeout(timers[id]);
  closeOthers(id);
  setOpen(id, true);
};
const onLeave = (id) => {
  if (!desktop.value) return;
  timers[id] = setTimeout(() => setOpen(id, false), CLOSE_DELAY);
};
const onFocusIn = (id) => {
  if (!desktop.value) return;
  closeOthers(id);
  setOpen(id, true);
};
const onFocusOut = (id) => { if (desktop.value) setOpen(id, false); };
const onToggle = (id) => setOpen(id, !openIds.has(id));

useEventListener(() => document, 'keydown', (e) => {
  if (e.key !== 'Escape') return;
  const id = megaMenus.find((m) => openIds.has(m.id))?.id;
  if (!id) return;
  setOpen(id, false);
  if (desktop.value) items.value[id]?.focusLink();
});

// Reset when crossing the breakpoint
watch(desktop, () => openIds.clear());
onBeforeUnmount(() => Object.values(timers).forEach(clearTimeout));
</script>

<template>
  <header class="site-header" :class="{ 'is-scrolled': headerScrolled, 'is-mega-open': megaOpen }" data-header>
    <nav class="navbar navbar-expand-xl site-header__nav" aria-label="Primary">
      <div class="container-fluid site-header__container">

        <a class="brand" href="/" aria-label="Aurelia Properties home">
          <span class="brand__mark">AURELIA</span>
        </a>

        <button class="navbar-toggler site-header__toggler" type="button"
                data-bs-toggle="offcanvas" data-bs-target="#mainMenu"
                aria-controls="mainMenu" aria-label="Open menu">
          <i class="bi bi-list" aria-hidden="true"></i>
        </button>

        <div class="offcanvas offcanvas-end site-menu" tabindex="-1" id="mainMenu" aria-labelledby="mainMenuLabel">
          <div class="offcanvas-header">
            <span class="brand__mark" id="mainMenuLabel">AURELIA</span>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Close menu"></button>
          </div>

          <div class="offcanvas-body site-menu__body">
            <ul class="navbar-nav site-menu__links">
              <li class="nav-item"><a class="nav-link" :href="navLinks.home.href">{{ navLinks.home.label }}</a></li>
              <li class="nav-item"><a class="nav-link" :href="navLinks.about.href">{{ navLinks.about.label }}</a></li>

              <MegaItem
                v-for="menu in megaMenus"
                :key="menu.id"
                :ref="(el) => (items[menu.id] = el)"
                :item="menu"
                :open="openIds.has(menu.id)"
                @enter="onEnter(menu.id)"
                @leave="onLeave(menu.id)"
                @focus-in="onFocusIn(menu.id)"
                @focus-out="onFocusOut(menu.id)"
                @toggle="onToggle(menu.id)"
              />

              <li class="nav-item"><a class="nav-link" :href="navLinks.gallery.href">{{ navLinks.gallery.label }}</a></li>
            </ul>

            <div class="site-menu__actions">
              <a class="header-search" href="#search" aria-label="Search the site">
                <i class="bi bi-search" aria-hidden="true"></i><span>Search</span>
              </a>

              <a class="btn btn-outline-header" href="#contact">Get in touch</a>

              <a class="icon-btn" href="tel:+0000000000" aria-label="Call us"><i class="bi bi-telephone" aria-hidden="true"></i></a>
              <a class="icon-btn" href="https://wa.me/0000000000" target="_blank" rel="noopener" aria-label="Chat on WhatsApp"><i class="bi bi-whatsapp" aria-hidden="true"></i></a>

              <div class="dropdown lang-switch">
                <button class="lang-switch__btn dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Change language">EN</button>
                <ul class="dropdown-menu dropdown-menu-end">
                  <li v-for="(lang, i) in languages" :key="lang.code">
                    <a class="dropdown-item" :class="{ active: i === 0 }" href="#" :lang="lang.code" :aria-current="i === 0 ? 'true' : undefined">{{ lang.label }}</a>
                  </li>
                </ul>
              </div>
            </div>
          </div>
        </div>

      </div>
    </nav>
  </header>
</template>
