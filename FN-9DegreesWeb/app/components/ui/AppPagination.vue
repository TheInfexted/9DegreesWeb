<template>
  <div class="flex items-center justify-between text-[12px] text-gray-500">
    <span>Page {{ current }} of {{ total }}</span>
    <div class="flex gap-1">
      <button
        class="px-3 py-1.5 rounded-lg border border-[#E8E8EC] hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed"
        :disabled="current <= 1"
        @click="$emit('change', current - 1)"
      >← Prev</button>
      <button
        class="px-3 py-1.5 rounded-lg border border-[#E8E8EC] hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed"
        :disabled="current >= total"
        @click="$emit('change', current + 1)"
      >Next →</button>
    </div>
  </div>
</template>

<script setup lang="ts">
defineProps<{ current: number; total: number }>()
defineEmits<{ change: [number] }>()
</script>
