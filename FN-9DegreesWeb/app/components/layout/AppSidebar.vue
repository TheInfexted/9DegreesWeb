<template>
  <aside class="flex flex-col w-[232px] bg-white/80 backdrop-blur-sm border-r border-border-soft h-dvh sticky top-0 shrink-0">
    <!-- Logo -->
    <div class="flex items-center gap-2.5 px-4 py-4 border-b border-border-soft">
      <div class="w-11 h-11 rounded-xl bg-ink overflow-hidden flex items-center justify-center shrink-0 shadow-soft">
        <img
          src="~/assets/img/9degree.png"
          alt="9 Degrees"
          class="h-full w-full object-contain origin-center scale-[2.35] select-none pointer-events-none"
        />
      </div>
      <div class="flex flex-col leading-tight">
        <span class="font-semibold text-[13px] tracking-[-0.005em] text-ink">9 Degrees</span>
        <span class="text-[10px] text-text-muted tracking-wide">Sales tracking</span>
      </div>
    </div>

    <!-- Main Nav -->
    <nav class="flex-1 px-2.5 py-4 overflow-y-auto">
      <p class="px-2.5 mb-1.5 text-[10px] font-semibold text-text-faint uppercase tracking-[0.1em]">Workspace</p>
      <div class="space-y-0.5">
        <SidebarItem v-for="item in mainNav" :key="item.to" v-bind="item" />
      </div>
      <p class="px-2.5 mt-5 mb-1.5 text-[10px] font-semibold text-text-faint uppercase tracking-[0.1em]">Manage</p>
      <div class="space-y-0.5">
        <SidebarItem v-for="item in mgmtNav" :key="item.to" v-bind="item" />
      </div>
    </nav>

    <SidebarUserPanel />
  </aside>
</template>

<script setup lang="ts">
import { mainSidebarNav, mgmtSidebarNav } from '~/config/sidebarNav'

const mainNav = mainSidebarNav
const mgmtNav = mgmtSidebarNav
</script>
