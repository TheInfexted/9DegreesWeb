<template>
  <aside class="flex flex-col w-[220px] bg-white border-r border-[#E8E8EC] h-screen sticky top-0 shrink-0">
    <!-- Logo -->
    <div class="flex items-center gap-2.5 px-4 py-4 border-b border-[#F0F0F0]">
      <div class="w-7 h-7 rounded-lg bg-[#0A0A0A] flex items-center justify-center shrink-0">
        <img src="~/assets/img/9degree.png" alt="9 Degrees" class="w-5 h-5 object-contain" />
      </div>
      <span class="font-bold text-[13px] tracking-wide text-ink">9 DEGREES</span>
    </div>

    <!-- Main Nav -->
    <nav class="flex-1 px-2 py-3 overflow-y-auto">
      <div class="space-y-0.5">
        <SidebarItem v-for="item in mainNav" :key="item.to" v-bind="item" />
      </div>
      <div class="my-2 border-t border-[#F0F0F0]" />
      <div class="space-y-0.5">
        <SidebarItem v-for="item in mgmtNav" :key="item.to" v-bind="item" />
      </div>
    </nav>

    <!-- User Footer -->
    <div class="px-2 py-3 border-t border-[#F0F0F0]">
      <div class="flex items-center gap-2 px-2.5 py-2 rounded-lg bg-[#FAFAFA]">
        <div class="w-7 h-7 rounded-full bg-[#00C4CC22] flex items-center justify-center text-[10px] font-bold text-[#007a80] shrink-0">
          {{ initials }}
        </div>
        <div class="min-w-0">
          <div class="text-[12px] font-semibold text-ink truncate">{{ auth.user?.username }}</div>
          <div class="text-[10px] text-gray-400 capitalize">{{ auth.user?.role }}</div>
        </div>
      </div>
    </div>
  </aside>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useAuthStore } from '~/stores/auth'

const auth = useAuthStore()
const initials = computed(() => (auth.user?.username ?? 'U').slice(0, 2).toUpperCase())

const mainNav = [
  { to: '/',            label: 'Dashboard'  },
  { to: '/sales',       label: 'Sales'      },
  { to: '/commissions', label: 'Commissions'},
  { to: '/payouts',     label: 'Payouts'    },
  { to: '/leaderboard', label: 'Leaderboard'},
]

const mgmtNav = [
  { to: '/ambassadors', label: 'Ambassadors'   },
  { to: '/teams',       label: 'Teams'         },
  { to: '/access',      label: 'Access & Roles'},
  { to: '/settings',    label: 'Settings'      },
]
</script>
