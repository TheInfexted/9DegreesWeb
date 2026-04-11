<template>
  <Transition name="drawer">
    <div v-if="open" class="fixed inset-0 z-50 lg:hidden">
      <!-- Backdrop -->
      <div class="absolute inset-0 bg-black/30" @click="$emit('close')" />
      <!-- Panel -->
      <div class="absolute left-0 top-0 bottom-0 w-[260px] bg-white shadow-xl flex flex-col">
        <div class="flex items-center justify-between px-4 py-3 border-b border-[#F0F0F0]">
          <div class="flex items-center gap-2">
            <div class="w-11 h-11 rounded-xl bg-[#0A0A0A] overflow-hidden flex items-center justify-center shrink-0">
              <img
                src="~/assets/img/9degree.png"
                alt="9 Degrees"
                class="h-full w-full object-contain origin-center scale-[2.35] select-none pointer-events-none"
              />
            </div>
            <span class="font-bold text-[13px] tracking-wide">9 DEGREES</span>
          </div>
          <button class="p-1 text-gray-400 hover:text-gray-600" @click="$emit('close')">✕</button>
        </div>
        <nav class="flex-1 overflow-y-auto px-2 py-3">
          <div class="space-y-0.5">
            <div v-for="item in allNav" :key="item.to" @click="$emit('close')">
              <SidebarItem v-bind="item" />
            </div>
          </div>
        </nav>
      </div>
    </div>
  </Transition>
</template>

<script setup lang="ts">
defineProps<{ open: boolean }>()
defineEmits<{ close: [] }>()

const allNav = [
  { to: '/',            label: 'Dashboard'   },
  { to: '/sales',       label: 'Sales'       },
  { to: '/commissions', label: 'Commissions' },
  { to: '/payouts',     label: 'Payouts'     },
  { to: '/leaderboard', label: 'Leaderboard' },
  { to: '/ambassadors', label: 'Ambassadors' },
  { to: '/teams',       label: 'Teams'       },
  { to: '/access',      label: 'Access & Roles' },
  { to: '/settings',    label: 'Settings'    },
]
</script>

<style scoped>
.drawer-enter-active, .drawer-leave-active { transition: opacity 0.2s; }
.drawer-enter-from, .drawer-leave-to { opacity: 0; }
</style>
