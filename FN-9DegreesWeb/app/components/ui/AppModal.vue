<template>
  <Teleport to="body">
    <Transition name="modal">
      <div v-if="modelValue" class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-0 sm:p-4">
        <div class="absolute inset-0 bg-black/30" @click="!persistent && $emit('update:modelValue', false)" />
        <div
          class="relative bg-white w-full sm:rounded-2xl shadow-xl overflow-hidden"
          :class="[
            'sm:max-w-' + (size ?? 'lg'),
            'rounded-t-2xl sm:rounded-2xl max-h-[90vh] flex flex-col'
          ]"
        >
          <!-- Header -->
          <div v-if="title" class="flex items-center justify-between px-5 py-4 border-b border-[#F0F0F0]">
            <h2 class="text-[16px] font-bold text-ink">{{ title }}</h2>
            <button
              v-if="!persistent"
              class="p-1 text-gray-400 hover:text-gray-600 rounded-lg"
              @click="$emit('update:modelValue', false)"
            >✕</button>
          </div>
          <!-- Body -->
          <div class="flex-1 overflow-y-auto px-5 py-4">
            <slot />
          </div>
          <!-- Footer -->
          <div v-if="$slots.footer" class="px-5 py-4 border-t border-[#F0F0F0] flex justify-end gap-2">
            <slot name="footer" />
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup lang="ts">
defineProps<{
  modelValue: boolean
  title?: string
  size?: 'sm' | 'md' | 'lg' | 'xl' | '2xl'
  persistent?: boolean
}>()
defineEmits<{ 'update:modelValue': [boolean] }>()
</script>

<style scoped>
.modal-enter-active, .modal-leave-active { transition: opacity 0.2s; }
.modal-enter-from, .modal-leave-to { opacity: 0; }
</style>
