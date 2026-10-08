<script setup>
import { nextTick, onMounted, ref, watch } from 'vue'
import { RouterLink, useRoute } from 'vue-router'

defineProps({
  collapsed: Boolean,
  mobileOpen: Boolean,
})
const emit = defineEmits(['toggle', 'close'])

// Add new pages here. `badge` is optional (pass real counts from your API/store).
const groups = [
  {
    label: 'Overview',
    items: [{ name: 'Dashboard', icon: 'bi-grid-1x2', to: '/' }],
  },
  {
    label: 'Sales',
    items: [
      { name: 'Contacts', icon: 'bi-people', to: '/contacts', badge: 21 },
      { name: 'Companies', icon: 'bi-building', to: '/companies' },
      { name: 'Leads', icon: 'bi-bullseye', to: '/leads', badge: 7 },
      { name: 'Deals', icon: 'bi-briefcase', to: '/deals', badge: 3 },
      { name: 'Quotes', icon: 'bi-file-earmark-text', to: '/quotes' },
      { name: 'Products', icon: 'bi-box-seam', to: '/products' },
    ],
  },
  {
    label: 'Pipelines',
    items: [
      { name: 'Pipelines', icon: 'bi-kanban-fill', to: '/pipelines' },
      { name: 'Forecasts', icon: 'bi-graph-up-arrow', to: '/forecasts' },
    ],
  },
  {
    label: 'Marketing',
    items: [
      { name: 'Campaigns', icon: 'bi-megaphone', to: '/campaigns' },
      { name: 'Email templates', icon: 'bi-envelope-paper', to: '/email-templates' },
      { name: 'Segments', icon: 'bi-funnel', to: '/segments' },
    ],
  },
  {
    label: 'Work',
    items: [
      { name: 'Tasks', icon: 'bi-check2-square', to: '/tasks' },
      { name: 'Calendar', icon: 'bi-calendar3', to: '/calendar' },
      { name: 'Meetings', icon: 'bi-camera-video', to: '/meetings' },
      { name: 'Notes', icon: 'bi-journal-text', to: '/notes' },
      { name: 'Documents', icon: 'bi-folder2-open', to: '/documents' },
      { name: 'Reports', icon: 'bi-bar-chart-line', to: '/reports' },
    ],
  },
  {
    label: 'Finance',
    items: [
      { name: 'Invoices', icon: 'bi-receipt', to: '/invoices' },
      { name: 'Payments', icon: 'bi-credit-card', to: '/payments' },
      { name: 'Expenses', icon: 'bi-wallet2', to: '/expenses' },
    ],
  },
  {
    label: 'Support',
    items: [
      { name: 'Tickets', icon: 'bi-life-preserver', to: '/tickets' },
      { name: 'Knowledge base', icon: 'bi-book', to: '/knowledge-base' },
    ],
  },
  {
    label: 'Team',
    items: [
      { name: 'Team members', icon: 'bi-person-badge', to: '/team' },
      { name: 'Roles & permissions', icon: 'bi-shield-lock', to: '/roles' },
      { name: 'Integrations', icon: 'bi-plug', to: '/integrations' },
    ],
  },
]

const route = useRoute()

const navEl = ref(null)

// With a long menu the active link can sit below the fold, so bring it into view.
const revealActive = () =>
  nextTick(() => navEl.value?.querySelector('.crm-nav-link.active')?.scrollIntoView({ block: 'nearest' }))

onMounted(revealActive)
watch(() => route.path, revealActive)

// "/leads" matches "/leads" and "/leads/create", but not "/leads-archive".
const isActive = (to) => (to === '/' ? route.path === '/' : route.path === to || route.path.startsWith(`${to}/`))
</script>

<template>
  <aside class="crm-sidebar" :class="{ 'is-collapsed': collapsed, 'is-open': mobileOpen }">
    <div class="crm-brand">
      <span class="crm-brand-mark"><i class="bi bi-people-fill"></i></span>
      <span class="crm-brand-name">CRM</span>
      <button
        type="button"
        class="crm-icon-btn crm-collapse-btn"
        :aria-label="collapsed ? 'Expand sidebar' : 'Collapse sidebar'"
        @click="emit('toggle')"
      >
        <i class="bi" :class="collapsed ? 'bi-chevron-bar-right' : 'bi-chevron-bar-left'"></i>
      </button>
    </div>

    <nav ref="navEl" class="crm-nav" aria-label="Main">
      <div v-for="group in groups" :key="group.label" class="crm-nav-group">
        <div class="crm-nav-label">{{ group.label }}</div>
        <RouterLink
          v-for="item in group.items"
          :key="item.to"
          :to="item.to"
          class="crm-nav-link"
          :class="{ active: isActive(item.to) }"
          :aria-current="isActive(item.to) ? 'page' : null"
          :title="collapsed ? item.name : null"
          @click="emit('close')"
        >
          <i class="bi" :class="item.icon"></i>
          <span class="crm-nav-text">{{ item.name }}</span>
          <span v-if="item.badge" class="crm-nav-badge">{{ item.badge }}</span>
        </RouterLink>
      </div>
    </nav>
  </aside>
</template>