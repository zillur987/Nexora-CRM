<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import { useRoute } from 'vue-router'

defineProps({
  user: { type: Object, default: () => ({ name: 'Admin User', email: 'admin@example.com' }) },
  notifications: { type: Number, default: 0 },
})
const emit = defineEmits(['menu', 'search', 'logout'])

const route = useRoute()
const title = computed(() => route.meta.title || route.name || 'Dashboard')

const query = ref('')
const searchEl = ref(null)
const open = ref(null) // 'new' | 'user' | null

const toggle = (name) => (open.value = open.value === name ? null : name)
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
    <button class="crm-icon-btn crm-menu-btn" @click="emit('menu')" aria-label="Open menu">
      <i class="bi bi-list"></i>
    </button>

    <div class="crm-page">
      <h1>{{ title }}</h1>
    </div>

    <form class="crm-search" @submit.prevent="emit('search', query)">
      <i class="bi bi-search"></i>
      <input ref="searchEl" v-model="query" type="search" placeholder="Search contacts, deals…" />
      <kbd>Ctrl K</kbd>
    </form>

    <div class="crm-actions">
      <div class="crm-dropdown">
        <button class="btn crm-btn-primary" @click.stop="toggle('new')">
          <i class="bi bi-plus-lg"></i><span>New</span>
        </button>
        <div v-if="open === 'new'" class="crm-menu" @click="closeAll">
          <router-link to="/contacts/create"><i class="bi bi-person-plus"></i>Contact</router-link>
          <router-link to="/leads/create"><i class="bi bi-bullseye"></i>Lead</router-link>
          <router-link to="/deals/create"><i class="bi bi-briefcase"></i>Deal</router-link>
        </div>
      </div>

      <button class="crm-icon-btn crm-bell" aria-label="Notifications">
        <i class="bi bi-bell"></i>
        <span v-if="notifications" class="crm-dot">{{ notifications > 9 ? '9+' : notifications }}</span>
      </button>

      <div class="crm-dropdown">
        <button class="crm-profile" @click.stop="toggle('user')">
          <span class="crm-avatar sm">{{ user.name[0] }}</span>
          <span class="crm-profile-name">{{ user.name }}</span>
          <i class="bi bi-chevron-down"></i>
        </button>
        <div v-if="open === 'user'" class="crm-menu right" @click="closeAll">
          <div class="crm-menu-head">
            <strong>{{ user.name }}</strong>
            <small>{{ user.email }}</small>
          </div>
          <router-link to="/profile"><i class="bi bi-person"></i>My profile</router-link>
          <router-link to="/settings"><i class="bi bi-gear"></i>Settings</router-link>
          <a href="#" @click.prevent="emit('logout')"><i class="bi bi-box-arrow-right"></i>Log out</a>
        </div>
      </div>
    </div>

    <div v-if="open" class="crm-backdrop" @click="closeAll"></div>
  </header>
</template>