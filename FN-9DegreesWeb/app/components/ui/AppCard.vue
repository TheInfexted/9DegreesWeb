<template>
  <div class="bg-white border border-[#E8E8EC] rounded-2xl p-4 lg:p-5 shadow-sm">
    <div class="text-[11px] font-bold text-gray-400 uppercase tracking-wide mb-2">{{ label }}</div>
    <div class="text-[22px] font-bold text-ink mb-1">
      <span v-if="prefix" class="text-ink">{{ prefix }}</span><span class="text-[#00A0A6]">{{ value }}</span>
    </div>
    <div v-if="trend" class="text-[11px] flex items-center gap-1" :class="trendClass">
      {{ trend }}
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{
  label: string
  value: string | number
  prefix?: string
  trend?: string
  trendDir?: 'up' | 'down' | 'neutral'
}>()

const trendClass = computed(() => ({
  'text-green-600': props.trendDir === 'up',
  'text-red-500':   props.trendDir === 'down',
  'text-gray-400':  props.trendDir === 'neutral' || !props.trendDir,
}))
</script>
