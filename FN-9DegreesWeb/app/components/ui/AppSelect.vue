<template>
  <select
    :value="modelValue"
    class="w-full px-3 py-2 border border-[#E0E0E0] rounded-lg text-[13px] text-ink bg-white outline-none focus:border-[#00C4CC] focus:ring-2 focus:ring-[#00C4CC]/10 transition-colors"
    @change="$emit('update:modelValue', ($event.target as HTMLSelectElement).value)"
  >
    <option v-if="placeholder" value="">{{ placeholder }}</option>
    <option v-for="opt in options" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
  </select>
</template>

<script setup lang="ts">
defineProps<{
  modelValue: string | number
  options: { value: string | number; label: string }[]
  placeholder?: string
}>()
defineEmits<{ 'update:modelValue': [string | number] }>()
</script>
