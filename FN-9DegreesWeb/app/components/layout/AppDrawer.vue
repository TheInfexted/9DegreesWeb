<template>
  <Transition name="drawer">
    <div v-if="open" class="fixed inset-0 z-50 lg:hidden">
      <!-- Backdrop -->
      <div class="absolute inset-0 bg-ink/30 backdrop-blur-[2px]" @click="$emit('close')" />
      <!-- Panel -->
      <div class="drawer-panel absolute left-0 top-0 bottom-0 w-[270px] bg-white shadow-pop flex flex-col">
        <div class="flex items-center justify-between px-4 py-4 border-b border-border-soft">
          <div class="flex items-center gap-2.5">
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
          <button
            class="p-1.5 -mr-1 text-text-muted hover:text-ink hover:bg-border-soft rounded-md active:scale-90 transition-all duration-150"
            aria-label="Close navigation"
            @click="$emit('close')"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M6 18L18 6"/>
            </svg>
          </button>
        </div>
        <nav class="flex-1 overflow-y-auto px-2.5 py-4 min-h-0">
          <div class="space-y-0.5">
            <div v-for="item in allNav" :key="item.to" @click="$emit('close')">
              <SidebarItem v-bind="item" />
            </div>
          </div>
        </nav>
        <SidebarUserPanel @logged-out="$emit('close')" />
      </div>
    </div>
  </Transition>
</template>

<script setup lang="ts">
import { allSidebarNav } from '~/config/sidebarNav'

defineProps<{ open: boolean }>()
defineEmits<{ close: [] }>()

const allNav = allSidebarNav
</script>

<style scoped>
.drawer-enter-active, .drawer-leave-active { transition: opacity 220ms ease; }
.drawer-enter-active .drawer-panel,
.drawer-leave-active .drawer-panel {
  transition: transform 280ms cubic-bezier(0.22, 0.61, 0.36, 1);
}
.drawer-enter-from, .drawer-leave-to { opacity: 0; }
.drawer-enter-from .drawer-panel { transform: translateX(-100%); }
.drawer-leave-to .drawer-panel   { transform: translateX(-100%); }
</style>
