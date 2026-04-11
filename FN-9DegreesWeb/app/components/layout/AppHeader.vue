<template>
  <header class="bg-white border-b border-[#E8E8EC] px-5 py-3 flex items-center justify-between sticky top-0 z-10">
    <div class="flex items-center gap-3">
      <!-- Hamburger (mobile only) -->
      <button
        class="lg:hidden p-1.5 rounded-lg hover:bg-gray-100 text-gray-500"
        @click="$emit('open-drawer')"
      >
        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>
        </svg>
      </button>
      <!-- Logo (mobile only) -->
      <div class="lg:hidden w-6 h-6 rounded-md bg-[#0A0A0A] flex items-center justify-center shrink-0">
        <img src="~/assets/img/9degree.png" alt="9 Degrees" class="w-4 h-4 object-contain" />
      </div>
      <h1 class="text-[16px] font-bold text-ink">{{ title }}</h1>
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
