<script setup>
import { ref, watch } from 'vue'
import Sidebar from './Sidebar.vue'
import Topbar from './Topbar.vue'
import './layout.css'

const collapsed = ref(localStorage.getItem('crm.sidebar') === '1')
const mobileOpen = ref(false)

watch(collapsed, (v) => localStorage.setItem('crm.sidebar', v ? '1' : '0'))

// Replace with real data from your auth store / Laravel API
const user = { name: 'Admin User', role: 'Administrator', email: 'admin@example.com' }
</script>

<template>
  <div class="crm-shell" :class="{ 'is-collapsed': collapsed }">
    <Sidebar
      :collapsed="collapsed"
      :mobile-open="mobileOpen"
      :user="user"
      @toggle="collapsed = !collapsed"
      @close="mobileOpen = false"
    />
    <div v-if="mobileOpen" class="crm-overlay" @click="mobileOpen = false"></div>

    <div class="crm-main">
      <Topbar :user="user" :notifications="3" @menu="mobileOpen = true" />
      <main class="crm-content">
        <router-view />
      </main>
    </div>
  </div>
</template>