<template>
  <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-semibold tracking-wide" :class="classes">
    <span class="w-1.5 h-1.5 rounded-full bg-current opacity-60" />
    <slot />
  </span>
</template>

<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{ variant: string }>()

const map: Record<string, string> = {
  confirmed: 'bg-[#e6fafa] text-[#007a80]',
  draft:     'bg-gray-100 text-gray-500',
  void:      'bg-red-50 text-red-600',
  paid:      'bg-green-50 text-green-700',
  unpaid:    'bg-amber-50 text-amber-700',
  active:    'bg-[#e6fafa] text-[#007a80]',
  inactive:  'bg-gray-100 text-gray-400',
  owner:     'bg-[#e6fafa] text-[#007a80]',
  admin:     'bg-purple-50 text-purple-700',
  leader:    'bg-blue-50 text-blue-700',
  ambassador:'bg-gray-100 text-gray-600',
}

const classes = computed(() => map[props.variant] ?? 'bg-gray-100 text-gray-500')
</script>
