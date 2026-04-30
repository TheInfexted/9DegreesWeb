<template>
  <div class="px-2.5 py-3 border-t border-border-soft">
    <div class="flex items-center gap-2.5 px-2.5 py-2 rounded-lg bg-border-soft/60">
      <div class="w-7 h-7 rounded-md bg-cyan-tint flex items-center justify-center text-[10px] font-semibold text-cyan-dark shrink-0 ring-1 ring-cyan/20">
        {{ initials }}
      </div>
      <div class="min-w-0 flex-1">
        <div class="text-[12px] font-medium text-ink truncate tracking-[-0.005em]">{{ auth.user?.username }}</div>
        <div class="text-[10px] text-text-muted capitalize tracking-wide">{{ auth.user?.role }}</div>
      </div>
    </div>
    <button
      type="button"
      class="mt-1.5 w-full flex items-center justify-center gap-1.5 px-2.5 py-2 rounded-md text-[12px] font-medium text-text-soft hover:bg-border-soft hover:text-ink active:scale-[0.98] transition-all duration-150"
      @click="onLogout"
    >
      <ArrowRightOnRectangleIcon class="w-3.5 h-3.5 shrink-0" aria-hidden="true" />
      Log out
    </button>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { ArrowRightOnRectangleIcon } from '@heroicons/vue/24/outline'
import { useAuthStore } from '~/stores/auth'

const emit = defineEmits<{ loggedOut: [] }>()

const auth = useAuthStore()
const initials = computed(() => (auth.user?.username ?? 'U').slice(0, 2).toUpperCase())

async function onLogout() {
  emit('loggedOut')
  auth.clear()
  await navigateTo('/login')
}
</script>
