<template>
  <aside class="flex flex-col w-[220px] bg-white border-r border-[#E8E8EC] h-screen sticky top-0 shrink-0">
    <!-- Logo -->
    <div class="flex items-center gap-2.5 px-4 py-4 border-b border-[#F0F0F0]">
      <div class="w-11 h-11 rounded-xl bg-[#0A0A0A] overflow-hidden flex items-center justify-center shrink-0">
        <img
          src="~/assets/img/9degree.png"
          alt="9 Degrees"
          class="h-full w-full object-contain origin-center scale-[2.35] select-none pointer-events-none"
        />
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

    <SidebarUserPanel />
  </aside>
</template>

<script setup lang="ts">
import { mainSidebarNav, mgmtSidebarNav } from '~/config/sidebarNav'

const mainNav = mainSidebarNav
const mgmtNav = mgmtSidebarNav
</script>
