<template>
  <div class="min-h-dvh bg-surface bg-grain flex items-center justify-center p-6 relative overflow-hidden">
    <div
      class="absolute -top-40 -right-40 w-[28rem] h-[28rem] rounded-full pointer-events-none"
      style="background: radial-gradient(closest-side, rgba(0,181,189,0.10), transparent 70%)"
      aria-hidden="true"
    />
    <div
      class="absolute -bottom-32 -left-32 w-[22rem] h-[22rem] rounded-full pointer-events-none"
      style="background: radial-gradient(closest-side, rgba(255,210,140,0.07), transparent 70%)"
      aria-hidden="true"
    />

    <div class="relative max-w-md w-full">
      <div class="surface shadow-pop p-8 sm:p-10">
        <div class="flex items-center gap-3 mb-7">
          <div class="w-10 h-10 rounded-xl bg-ink overflow-hidden flex items-center justify-center shadow-soft">
            <img
              src="~/assets/img/9degree.png"
              alt=""
              class="h-full w-full object-contain origin-center scale-[2.1] select-none pointer-events-none"
            />
          </div>
          <div class="flex flex-col leading-tight">
            <span class="text-[12.5px] font-semibold text-ink tracking-[-0.005em]">9 Degrees</span>
            <span class="text-[10px] text-text-muted uppercase tracking-[0.1em]">Sales tracking</span>
          </div>
        </div>

        <p class="text-[11px] font-semibold text-text-muted uppercase tracking-[0.12em] tabular">Error · {{ statusCode }}</p>
        <h1 class="text-[28px] font-semibold text-ink tracking-tightest leading-[1.1] mt-1.5">
          {{ headline }}
        </h1>
        <p class="text-[13.5px] text-text-soft mt-2.5 leading-relaxed">
          {{ description }}
        </p>

        <div class="mt-7 flex gap-2">
          <button class="btn-secondary" @click="back">Go back</button>
          <button class="btn-primary" @click="home">Take me home</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{
  error: { statusCode?: number; statusMessage?: string; message?: string } | null
}>()

const statusCode = computed(() => props.error?.statusCode ?? 500)

const headline = computed(() => {
  if (statusCode.value === 404) return 'We can’t find that page.'
  if (statusCode.value === 403) return 'You don’t have access to this.'
  if (statusCode.value === 401) return 'Your session has expired.'
  return 'Something went wrong on our end.'
})

const description = computed(() => {
  if (statusCode.value === 404) return 'The page you’re looking for might have been moved or deleted. Try heading back to the dashboard.'
  if (statusCode.value === 403) return 'Your account doesn’t have permission to view this page. If you think that’s wrong, ask an owner.'
  if (statusCode.value === 401) return 'Sign in again to keep going.'
  return props.error?.statusMessage || props.error?.message || 'Refresh the page or try again in a moment.'
})

function home() {
  if (statusCode.value === 401) return clearError({ redirect: '/login' })
  return clearError({ redirect: '/' })
}

function back() {
  if (window.history.length > 1) window.history.back()
  else home()
}
</script>
