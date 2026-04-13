<template>
  <div class="px-2 py-3 border-t border-[#F0F0F0]">
    <div class="flex items-center gap-2 px-2.5 py-2 rounded-lg bg-[#FAFAFA]">
      <div class="w-7 h-7 rounded-full bg-[#00C4CC22] flex items-center justify-center text-[10px] font-bold text-[#007a80] shrink-0">
        {{ initials }}
      </div>
      <div class="min-w-0 flex-1">
        <div class="text-[12px] font-semibold text-ink truncate">{{ auth.user?.username }}</div>
        <div class="text-[10px] text-gray-400 capitalize">{{ auth.user?.role }}</div>
      </div>
    </div>
    <button
      type="button"
      class="mt-2 w-full flex items-center justify-center gap-1.5 px-2.5 py-2 rounded-lg text-[12px] font-medium text-gray-500 hover:bg-gray-100 hover:text-gray-700 transition-colors"
      @click="onLogout"
    >
      <ArrowRightOnRectangleIcon class="w-4 h-4 shrink-0" aria-hidden="true" />
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
