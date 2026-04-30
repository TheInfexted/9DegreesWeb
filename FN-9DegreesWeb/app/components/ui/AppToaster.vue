<template>
  <Teleport to="body">
    <div
      class="fixed bottom-5 right-5 z-[60] flex flex-col gap-2 max-w-[min(380px,calc(100vw-2.5rem))] pointer-events-none"
      role="region"
      aria-live="polite"
      aria-label="Notifications"
    >
      <TransitionGroup name="toast" tag="div" class="flex flex-col gap-2">
        <article
          v-for="t in toasts"
          :key="t.id"
          class="toast pointer-events-auto bg-white ring-1 ring-border shadow-pop rounded-lg overflow-hidden"
        >
          <div class="flex gap-3 p-3.5 pr-2.5">
            <span
              class="shrink-0 w-7 h-7 rounded-md flex items-center justify-center"
              :class="iconBg(t.tone)"
              aria-hidden="true"
            >
              <svg v-if="t.tone === 'success'" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.4" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
              <svg v-else-if="t.tone === 'error'" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
              <svg v-else class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </span>
            <div class="flex-1 min-w-0">
              <p class="text-[12.5px] font-semibold text-ink tracking-[-0.005em]">{{ t.title }}</p>
              <p v-if="t.message" class="text-[12px] text-text-soft mt-0.5 whitespace-pre-line break-words">{{ t.message }}</p>
            </div>
            <button
              type="button"
              class="shrink-0 w-6 h-6 -mt-1 -mr-1 rounded-md text-text-muted hover:text-ink hover:bg-border-soft active:scale-90 transition-all duration-150 flex items-center justify-center"
              aria-label="Dismiss"
              @click="dismiss(t.id)"
            >
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M6 18L18 6"/></svg>
            </button>
          </div>
          <div class="h-[2px]" :class="barBg(t.tone)" />
        </article>
      </TransitionGroup>
    </div>
  </Teleport>
</template>

<script setup lang="ts">
const { toasts, dismiss } = useToast()

function iconBg(tone: 'info' | 'success' | 'error'): string {
  if (tone === 'success') return 'bg-[#EAF7EE] text-[#1F7A3F] ring-1 ring-inset ring-[#C9E6D2]'
  if (tone === 'error')   return 'bg-[#FDF2F1] text-[#B83227] ring-1 ring-inset ring-[#F1D8D5]'
  return 'bg-cyan-tint text-cyan-dark ring-1 ring-inset ring-[#00B5BD]/20'
}

function barBg(tone: 'info' | 'success' | 'error'): string {
  if (tone === 'success') return 'bg-[#2E9F5C]'
  if (tone === 'error')   return 'bg-[#DC4438]'
  return 'bg-cyan'
}
</script>

<style scoped>
.toast { transform-origin: bottom right; }
.toast-enter-active { transition: transform 280ms cubic-bezier(0.34, 1.56, 0.64, 1), opacity 200ms ease-out; }
.toast-leave-active { transition: transform 220ms ease-in, opacity 180ms ease-in; }
.toast-enter-from   { transform: translateY(12px) scale(0.96); opacity: 0; }
.toast-leave-to     { transform: translateX(40px); opacity: 0; }
.toast-move         { transition: transform 220ms ease; }
</style>
