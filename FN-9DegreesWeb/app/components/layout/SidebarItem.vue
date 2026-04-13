<template>
  <NuxtLink
    :to="to"
    class="flex items-center gap-2.5 px-2.5 py-2 rounded-lg text-[13px] transition-colors"
    :class="isActive
      ? 'bg-[#00C4CC12] text-[#007a80] font-semibold'
      : 'text-gray-500 hover:bg-gray-50 hover:text-gray-700'"
  >
    <component
      :is="Icon"
      class="w-[14px] h-[14px] shrink-0"
      :class="isActive ? 'text-[#00C4CC]' : 'text-gray-400'"
      aria-hidden="true"
    />
    {{ label }}
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
