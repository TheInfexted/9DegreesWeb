<template>
  <div class="flex min-h-screen bg-[#F0F2F5]">
    <!-- Desktop Sidebar -->
    <AppSidebar class="hidden lg:flex" />

    <!-- Main -->
    <div class="flex-1 flex flex-col min-w-0">
      <AppHeader :title="pageTitle" @open-drawer="drawerOpen = true">
        <template #actions>
          <slot name="header-actions" />
        </template>
      </AppHeader>

      <main class="flex-1 p-4 lg:p-6">
        <slot />
      </main>
    </div>

    <!-- Mobile Drawer -->
    <AppDrawer :open="drawerOpen" @close="drawerOpen = false" />
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { useRoute } from 'vue-router'

const drawerOpen = ref(false)
const route      = useRoute()

const titles: Record<string, string> = {
  '/':            'Dashboard',
  '/sales':       'Sales',
  '/commissions': 'Commissions',
  '/payouts':     'Payouts',
  '/leaderboard': 'Leaderboard',
  '/ambassadors': 'Ambassadors',
  '/teams':       'Teams',
  '/access':      'Access & Roles',
  '/settings':    'Settings',
}

const pageTitle = computed(() => titles[route.path] ?? '9 Degrees')
</script>
