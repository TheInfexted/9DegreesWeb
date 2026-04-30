<template>
  <div class="flex items-center justify-between text-[12px] text-text-soft">
    <span class="tabular">Page <span class="text-ink font-medium">{{ current }}</span> of <span class="text-ink font-medium">{{ total }}</span></span>
    <div class="flex gap-1.5">
      <button
        type="button"
        class="px-3 py-1.5 rounded-md border border-border bg-white hover:bg-border-soft hover:border-[#D8D7D2] disabled:opacity-40 disabled:cursor-not-allowed disabled:hover:bg-white active:scale-[0.97] transition-all duration-150 inline-flex items-center gap-1"
        :disabled="current <= 1"
        @click="$emit('change', current - 1)"
      >
        <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
        </svg>
        Prev
      </button>
      <button
        type="button"
        class="px-3 py-1.5 rounded-md border border-border bg-white hover:bg-border-soft hover:border-[#D8D7D2] disabled:opacity-40 disabled:cursor-not-allowed disabled:hover:bg-white active:scale-[0.97] transition-all duration-150 inline-flex items-center gap-1"
        :disabled="current >= total"
        @click="$emit('change', current + 1)"
      >
        Next
        <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
          <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
        </svg>
      </button>
    </div>
  </div>
</template>

<script setup lang="ts">
defineProps<{ current: number; total: number }>()
defineEmits<{ change: [number] }>()
</script>
