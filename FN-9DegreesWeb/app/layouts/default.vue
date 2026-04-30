<template>
  <div class="flex min-h-dvh bg-surface bg-grain">
    <!-- Skip to content (keyboard accessibility) -->
    <a href="#main-content" class="skip-link">Skip to main content</a>

    <!-- Desktop Sidebar -->
    <AppSidebar class="hidden lg:flex" />

    <!-- Main -->
    <div class="flex-1 flex flex-col min-w-0 relative z-[1]">
      <AppHeader :title="pageTitle" @open-drawer="drawerOpen = true">
        <template #actions>
          <slot name="header-actions" />
        </template>
      </AppHeader>

      <main id="main-content" class="flex-1 p-4 lg:p-7">
        <div class="max-w-[1400px] mx-auto w-full">
          <slot />
        </div>
      </main>
    </div>

    <!-- Mobile Drawer -->
    <AppDrawer :open="drawerOpen" @close="drawerOpen = false" />

    <!-- Confirm Dialog -->
    <AppModal v-model="confirmState.open" :title="confirmState.title" size="sm" persistent>
      <p class="text-[13px] text-text-soft">{{ confirmState.message }}</p>
      <template #footer>
        <button class="btn-secondary" @click="respond(false)">Cancel</button>
        <button
          :class="confirmState.tone === 'danger' ? 'btn-danger !bg-[#DC4438] !text-white !border-[#DC4438] hover:!bg-[#C73B30] hover:!border-[#C73B30]' : 'btn-primary'"
          @click="respond(true)"
        >{{ confirmState.confirmLabel ?? 'Confirm' }}</button>
      </template>
    </AppModal>

    <!-- Toaster -->
    <AppToaster />
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
const { state: confirmState, respond } = useConfirm()
</script>

<style scoped>
.skip-link {
  position: absolute;
  top: -40px;
  left: 16px;
  padding: 8px 14px;
  background: #0E0F10;
  color: white;
  border-radius: 8px;
  font-size: 12.5px;
  font-weight: 500;
  z-index: 100;
  transition: top 180ms cubic-bezier(0.22, 0.61, 0.36, 1);
}
.skip-link:focus { top: 12px; outline: none; }
</style>
