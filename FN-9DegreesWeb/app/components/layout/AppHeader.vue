<template>
  <header class="bg-white/85 backdrop-blur-md border-b border-border-soft px-5 lg:px-7 py-3.5 flex items-center justify-between sticky top-0 z-10">
    <div class="flex items-center gap-3">
      <!-- Hamburger (mobile only) -->
      <button
        class="lg:hidden p-1.5 rounded-md hover:bg-border-soft text-text-soft active:scale-95 transition-all duration-150"
        @click="$emit('open-drawer')"
        aria-label="Open navigation"
      >
        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16M4 12h16M4 17h16"/>
        </svg>
      </button>
      <!-- Logo (mobile only) -->
      <div class="lg:hidden w-10 h-10 rounded-xl bg-ink overflow-hidden flex items-center justify-center shrink-0 shadow-soft">
        <img
          src="~/assets/img/9degree.png"
          alt="9 Degrees"
          class="h-full w-full object-contain origin-center scale-[2.35] select-none pointer-events-none"
        />
      </div>
      <h1 class="text-[17px] font-semibold text-ink tracking-tightest">{{ title }}</h1>
    </div>
    <div class="flex items-center gap-2">
      <slot name="actions" />
    </div>
  </header>
</template>

<script setup lang="ts">
defineProps<{ title: string }>()
defineEmits<{ 'open-drawer': [] }>()
</script>
