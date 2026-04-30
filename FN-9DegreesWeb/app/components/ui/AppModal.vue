<template>
  <Teleport to="body">
    <Transition name="modal">
      <div v-if="modelValue" class="fixed inset-0 z-50 flex items-end sm:items-center justify-center p-0 sm:p-4">
        <div
          class="absolute inset-0 bg-ink/30 backdrop-blur-[2px]"
          @click="!persistent && $emit('update:modelValue', false)"
        />
        <div
          class="modal-panel relative bg-white w-full sm:rounded-2xl shadow-pop overflow-hidden ring-1 ring-border"
          :class="[sizeClass, 'rounded-t-2xl sm:rounded-2xl max-h-[90dvh] flex flex-col']"
        >
          <!-- Header -->
          <div v-if="title" class="flex items-center justify-between px-5 py-4 border-b border-border-soft">
            <h2 class="text-[15.5px] font-semibold text-ink tracking-tightest">{{ title }}</h2>
            <button
              v-if="!persistent"
              class="p-1.5 -mr-1.5 text-text-muted hover:text-ink hover:bg-border-soft rounded-md active:scale-90 transition-all duration-150"
              aria-label="Close"
              @click="$emit('update:modelValue', false)"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M6 18L18 6"/>
              </svg>
            </button>
          </div>
          <!-- Body -->
          <div class="flex-1 overflow-y-auto px-5 py-4">
            <slot />
          </div>
          <!-- Footer -->
          <div v-if="$slots.footer" class="px-5 py-3.5 border-t border-border-soft bg-border-soft/30 flex justify-end gap-2">
            <slot name="footer" />
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{
  modelValue: boolean
  title?: string
  size?: 'sm' | 'md' | 'lg' | 'xl' | '2xl'
  persistent?: boolean
}>()
defineEmits<{ 'update:modelValue': [boolean] }>()

const sizeMap: Record<string, string> = {
  sm: 'sm:max-w-sm', md: 'sm:max-w-md', lg: 'sm:max-w-lg',
  xl: 'sm:max-w-xl', '2xl': 'sm:max-w-2xl',
}
const sizeClass = computed(() => sizeMap[props.size ?? 'lg'])
</script>

<style scoped>
.modal-enter-active, .modal-leave-active { transition: opacity 220ms cubic-bezier(0.22, 0.61, 0.36, 1); }
.modal-enter-active .modal-panel,
.modal-leave-active .modal-panel {
  transition: transform 280ms cubic-bezier(0.34, 1.56, 0.64, 1), opacity 220ms ease;
}
.modal-enter-from, .modal-leave-to { opacity: 0; }
.modal-enter-from .modal-panel { transform: translateY(8px) scale(0.97); opacity: 0; }
.modal-leave-to .modal-panel   { transform: translateY(8px) scale(0.98); opacity: 0; }
</style>
