<template>
  <NuxtLink
    :to="to"
    class="group relative flex items-center gap-2.5 pl-3 pr-2.5 py-2 rounded-md text-[13px] transition-all duration-150"
    :class="isActive
      ? 'bg-cyan-tint text-ink font-medium'
      : 'text-text-soft hover:bg-border-soft hover:text-ink'"
  >
    <!-- Active indicator bar -->
    <span
      v-if="isActive"
      class="absolute left-0 top-1.5 bottom-1.5 w-[3px] rounded-r-full bg-cyan"
      aria-hidden="true"
    />
    <component
      :is="Icon"
      class="w-[15px] h-[15px] shrink-0 transition-colors"
      :class="isActive ? 'text-cyan-dark' : 'text-text-muted group-hover:text-text-soft'"
      aria-hidden="true"
    />
    <span class="tracking-[-0.005em]">{{ label }}</span>
  </NuxtLink>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { sidebarIconMap, type SidebarIconKey } from '~/config/sidebarNav'

const props = defineProps<{ to: string; label: string; icon: SidebarIconKey }>()
const route = useRoute()
const isActive = computed(() => route.path === props.to)
const Icon = computed(() => sidebarIconMap[props.icon])
</script>
