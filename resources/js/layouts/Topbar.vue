<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'

const props = defineProps({
  // `auth.user` is null until the session loads, so every access below is null-safe.
  user: { type: Object, default: null },
  notifications: { type: Number, default: 0 },
})
const emit = defineEmits(['menu', 'search', 'logout'])

const route = useRoute()
const title = computed(() => route.meta.title || route.name || 'Dashboard')

const name = computed(() => props.user?.name || 'User')
const email = computed(() => props.user?.email || '')
const initial = computed(() => name.value[0].toUpperCase())

const shortcutHint = /Mac|iPhone|iPad/i.test(navigator.userAgent) ? '⌘ K' : 'Ctrl K'

const query = ref('')
const searchEl = ref(null)
const open = ref(null) // 'new' | 'user' | null

const toggle = (key) => (open.value = open.value === key ? null : key)
const closeAll = () => (open.value = null)

function onKey(e) {
  if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
    e.preventDefault()
    searchEl.value?.focus()
  }
  if (e.key === 'Escape') closeAll()
}

onMounted(() => window.addEventListener('keydown', onKey))
onBeforeUnmount(() => window.removeEventListener('keydown', onKey))
</script>

<template>
  <header class="crm-topbar">
    <button type="button" class="crm-icon-btn crm-menu-btn" aria-label="Open menu" @click="emit('menu')">
      <i class="bi bi-list"></i>
    </button>

    <div class="crm-page">
      <h1>{{ title }}</h1>
    </div>

    <form class="crm-search" role="search" @submit.prevent="emit('search', query)">
      <i class="bi bi-search"></i>
      <input ref="searchEl" v-model.trim="query" type="search" placeholder="Search contacts, deals…" aria-label="Search" />
      <kbd>{{ shortcutHint }}</kbd>
    </form>

    <div class="crm-actions">
      <div class="crm-dropdown">
        <button
          type="button"
          class="btn crm-btn-primary"
          aria-haspopup="menu"
          :aria-expanded="open === 'new'"
          @click.stop="toggle('new')"
        >
          <i class="bi bi-plus-lg"></i><span>New</span>
        </button>
        <div v-if="open === 'new'" class="crm-menu" role="menu" @click="closeAll">
          <router-link to="/contacts/create" role="menuitem"><i class="bi bi-person-plus"></i>Contact</router-link>
          <router-link to="/leads/create" role="menuitem"><i class="bi bi-bullseye"></i>Lead</router-link>
          <router-link to="/deals/create" role="menuitem"><i class="bi bi-briefcase"></i>Deal</router-link>
        </div>
      </div>

      <button type="button" class="crm-icon-btn crm-bell" aria-label="Notifications">
        <i class="bi bi-bell"></i>
        <span v-if="notifications" class="crm-dot">{{ notifications > 9 ? '9+' : notifications }}</span>
      </button>

      <div class="crm-dropdown">
        <button
          type="button"
          class="crm-profile"
          aria-haspopup="menu"
          :aria-expanded="open === 'user'"
          @click.stop="toggle('user')"
        >
          <span class="crm-avatar sm">{{ initial }}</span>
          <span class="crm-profile-name">{{ name }}</span>
          <i class="bi bi-chevron-down"></i>
        </button>
        <div v-if="open === 'user'" class="crm-menu right" role="menu" @click="closeAll">
          <div class="crm-menu-head">
            <strong>{{ name }}</strong>
            <small v-if="email">{{ email }}</small>
          </div>
          <router-link to="/profile" role="menuitem"><i class="bi bi-person"></i>My profile</router-link>
          <router-link to="/settings" role="menuitem"><i class="bi bi-gear"></i>Settings</router-link>
          <a href="#" role="menuitem" @click.prevent="emit('logout')"><i class="bi bi-box-arrow-right"></i>Log out</a>
        </div>
      </div>
    </div>

    <div v-if="open" class="crm-backdrop" @click="closeAll"></div>
  </header>
</template>