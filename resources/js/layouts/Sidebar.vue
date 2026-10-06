<script setup>
import { computed } from 'vue'
import { RouterLink, useRoute } from 'vue-router'

const props = defineProps({
  collapsed: Boolean,
  mobileOpen: Boolean,
  // `auth.user` is null until the session loads, so every access below is null-safe.
  user: { type: Object, default: null },
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
      { name: 'Leads', icon: 'bi-bullseye', to: '/leads', badge: 7 },
      { name: 'Deals', icon: 'bi-briefcase', to: '/deals', badge: 3 },
    ],
  },
  {
    label: 'Pipelines',
    items: [{ name: 'Pipelines', icon: 'bi-kanban-fill', to: '/pipelines' }],
  },
  {
    label: 'Work',
    items: [
      { name: 'Tasks', icon: 'bi-check2-square', to: '/tasks' },
      { name: 'Calendar', icon: 'bi-calendar3', to: '/calendar' },
      { name: 'Reports', icon: 'bi-bar-chart-line', to: '/reports' },
    ],
  },
]

const route = useRoute()

// "/leads" matches "/leads" and "/leads/create", but not "/leads-archive".
const isActive = (to) => (to === '/' ? route.path === '/' : route.path === to || route.path.startsWith(`${to}/`))

const name = computed(() => props.user?.name || 'User')
const role = computed(() => (typeof props.user?.role === 'string' ? props.user.role : ''))
const initials = computed(() =>
  name.value
    .split(' ')
    .filter(Boolean)
    .map((w) => w[0])
    .slice(0, 2)
    .join('')
    .toUpperCase(),
)
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

    <nav class="crm-nav" aria-label="Main">
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

    <div class="crm-sidebar-foot">
      <RouterLink
        to="/settings"
        class="crm-nav-link"
        :class="{ active: isActive('/settings') }"
        :aria-current="isActive('/settings') ? 'page' : null"
        :title="collapsed ? 'Settings' : null"
        @click="emit('close')"
      >
        <i class="bi bi-gear"></i><span class="crm-nav-text">Settings</span>
      </RouterLink>
      <div class="crm-user">
        <span class="crm-avatar">{{ initials }}</span>
        <div class="crm-user-meta">
          <strong>{{ name }}</strong>
          <small v-if="role">{{ role }}</small>
        </div>
      </div>
    </div>
  </aside>
</template>