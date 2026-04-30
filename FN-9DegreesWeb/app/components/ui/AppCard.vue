<template>
  <div class="surface surface-hover relative p-5 lg:p-6 overflow-hidden">
    <!-- subtle corner glow on the value -->
    <div
      v-if="accent !== false"
      class="absolute -top-12 -right-12 w-40 h-40 rounded-full pointer-events-none"
      style="background: radial-gradient(closest-side, rgba(0,181,189,0.08), transparent 70%)"
      aria-hidden="true"
    />
    <div class="relative">
      <div class="text-[10.5px] font-semibold text-text-muted uppercase tracking-[0.1em] mb-3">{{ label }}</div>
      <div class="text-[26px] lg:text-[28px] font-semibold text-ink mb-1 leading-none tracking-tightest tabular">
        <span v-if="prefix" class="text-text-faint font-medium text-[20px] mr-0.5">{{ prefix }}</span>{{ value }}
      </div>
      <div v-if="trend" class="text-[11px] flex items-center gap-1 mt-1.5 tabular" :class="trendClass">
        <span aria-hidden="true">{{ trendArrow }}</span>{{ trend }}
      </div>
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
  /** Render the subtle corner accent glow. Default true. */
  accent?: boolean
}>()

const trendClass = computed(() => ({
  'text-[#2E9F5C]': props.trendDir === 'up',
  'text-[#DC4438]': props.trendDir === 'down',
  'text-text-muted': props.trendDir === 'neutral' || !props.trendDir,
}))

const trendArrow = computed(() =>
  props.trendDir === 'up' ? '↑' : props.trendDir === 'down' ? '↓' : '·'
)
</script>
