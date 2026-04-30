<template>
  <span
    class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md text-[10.5px] font-semibold tracking-[0.02em] ring-1 ring-inset"
    :class="classes"
  >
    <span class="w-1 h-1 rounded-full bg-current" />
    <slot />
  </span>
</template>

<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{ variant: string }>()

const map: Record<string, string> = {
  confirmed: 'bg-cyan-tint text-[#00696F] ring-[#00B5BD]/20',
  draft:     'bg-border-soft text-text-soft ring-border',
  void:      'bg-[#FDF2F1] text-[#B83227] ring-[#F1D8D5]',
  paid:      'bg-[#EAF7EE] text-[#1F7A3F] ring-[#C9E6D2]',
  unpaid:    'bg-[#FDF6E7] text-[#9C6611] ring-[#F0DCB1]',
  active:    'bg-cyan-tint text-[#00696F] ring-[#00B5BD]/20',
  inactive:  'bg-border-soft text-text-faint ring-border',
  owner:     'bg-cyan-tint text-[#00696F] ring-[#00B5BD]/20',
  admin:     'bg-[#F3EFFB] text-[#5B3FA1] ring-[#DBD0F0]',
  leader:    'bg-[#EBF1FB] text-[#2A5BA0] ring-[#C8D7EE]',
  ambassador:'bg-border-soft text-text-soft ring-border',
}

const classes = computed(() => map[props.variant] ?? 'bg-border-soft text-text-soft ring-border')
</script>
