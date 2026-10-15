<script setup>
import { computed, reactive, ref, watch, onBeforeUnmount } from 'vue';
import MegaItem from './MegaItem.vue';
import { useHeaderContent } from '../composables/useSiteContent.js';
import { headerScrolled } from '../composables/useHeaderState.js';
import { useMediaQuery } from '../composables/useMediaQuery.js';
import { useEventListener } from '../composables/useEventListener.js';

/**
 * Desktop (>=1200px): mega menus open on hover / keyboard focus and close after a short delay
 * (so the pointer can travel into the panel) or on Escape. While open the header gets `.is-mega-open`.
 * Mobile: the chevron button toggles an accordion inside the offcanvas (Bootstrap handles the offcanvas).
 */
const CLOSE_DELAY = 160;
const header = useHeaderContent();   // menu, brand, actions and languages come from the CMS API
console.log(header.data, 'header data')
const menu = computed(() => header.data.menu);
const brand = computed(() => header.data.brand);
const actions = computed(() => header.data.actions);
const languages = computed(() => header.data.languages);
const megaMenus = computed(() => menu.value.filter((m) => m.type === 'mega'));
const phoneHref = computed(() => (actions.value.phone ? `tel:${actions.value.phone.replace(/[^\d+]/g, '')}` : null));
const whatsappHref = computed(() => (actions.value.whatsapp ? `https://wa.me/${actions.value.whatsapp}` : null));
const relFor = (target) => (target === '_blank' ? 'noopener' : undefined);

const desktop = useMediaQuery('(min-width: 1200px)');
const openIds = reactive(new Set());
const items = ref({});
const timers = {};

const megaOpen = computed(() => desktop.value && openIds.size > 0);

const setOpen = (id, open) => (open ? openIds.add(id) : openIds.delete(id));
const closeOthers = (keep) => megaMenus.value.forEach(({ id }) => {
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
  const id = megaMenus.value.find((m) => openIds.has(m.id))?.id;
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

        <a class="brand" href="/" :aria-label="`${brand.name} home`">
          <img v-if="brand.logo_url" :src="brand.logo_url" :alt="brand.name" style="height: 2.6rem; width: auto; display: block;">
          <span v-else class="brand__mark">{{ brand.name }}</span>
        </a>

        <button class="navbar-toggler site-header__toggler" type="button"
                data-bs-toggle="offcanvas" data-bs-target="#mainMenu"
                aria-controls="mainMenu" aria-label="Open menu">
          <i class="bi bi-list" aria-hidden="true"></i>
        </button>

        <div class="offcanvas offcanvas-end site-menu" tabindex="-1" id="mainMenu" aria-labelledby="mainMenuLabel">
          <div class="offcanvas-header">
            <span class="brand__mark" id="mainMenuLabel">{{ brand.name }}</span>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Close menu"></button>
          </div>

          <div class="offcanvas-body site-menu__body">
            <ul class="navbar-nav site-menu__links">
              <template v-for="item in menu" :key="item.id">
                <MegaItem
                  v-if="item.type === 'mega'"
                  :ref="(el) => (items[item.id] = el)"
                  :item="item"
                  :open="openIds.has(item.id)"
                  @enter="onEnter(item.id)"
                  @leave="onLeave(item.id)"
                  @focus-in="onFocusIn(item.id)"
                  @focus-out="onFocusOut(item.id)"
                  @toggle="onToggle(item.id)"
                />
                <li v-else class="nav-item">
                  <a class="nav-link" :href="item.url" :target="item.target" :rel="relFor(item.target)">{{ item.label }}</a>
                </li>
              </template>
            </ul>

            <div class="site-menu__actions">
              <a v-if="actions.search_label" class="header-search" :href="actions.search_url" aria-label="Search the site">
                <i class="bi bi-search" aria-hidden="true"></i><span>{{ actions.search_label }}</span>
              </a>

              <a v-if="actions.cta_label" class="btn btn-outline-header" :href="actions.cta_url">{{ actions.cta_label }}</a>

              <a v-if="phoneHref" class="icon-btn" :href="phoneHref" aria-label="Call us"><i class="bi bi-telephone" aria-hidden="true"></i></a>
              <a v-if="whatsappHref" class="icon-btn" :href="whatsappHref" target="_blank" rel="noopener" aria-label="Chat on WhatsApp"><i class="bi bi-whatsapp" aria-hidden="true"></i></a>

              <div v-if="languages.length" class="dropdown lang-switch">
                <button class="lang-switch__btn dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Change language">{{ languages[0].short }}</button>
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
