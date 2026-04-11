<template>
  <div>
    <NuxtLayout name="auth">
      <div class="w-full max-w-sm bg-white rounded-2xl shadow-lg p-8 border border-[#E8E8EC]">
        <div class="flex flex-col items-center mb-7">
          <div class="w-16 h-16 rounded-2xl bg-[#0A0A0A] flex items-center justify-center mb-4">
            <img src="~/assets/img/9degree.png" alt="9 Degrees" class="w-10 h-10 object-contain" />
          </div>
          <h1 class="text-[20px] font-bold text-ink">9 Degrees</h1>
          <p class="text-[13px] text-gray-400 mt-1">Sign in to your account</p>
        </div>

        <form class="space-y-4" @submit.prevent="handleLogin">
          <div>
            <label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Username</label>
            <input
              v-model="form.username"
              type="text"
              autocomplete="username"
              required
              class="w-full px-3 py-2.5 border border-[#E0E0E0] rounded-lg text-[13px] outline-none focus:border-[#00C4CC] focus:ring-2 focus:ring-[#00C4CC]/10 transition-colors"
              placeholder="Enter your username"
            />
          </div>
          <div>
            <label class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Password</label>
            <input
              v-model="form.password"
              type="password"
              autocomplete="current-password"
              required
              class="w-full px-3 py-2.5 border border-[#E0E0E0] rounded-lg text-[13px] outline-none focus:border-[#00C4CC] focus:ring-2 focus:ring-[#00C4CC]/10 transition-colors"
              placeholder="Enter your password"
            />
          </div>

          <p v-if="error" class="text-[12px] text-red-500 bg-red-50 rounded-lg px-3 py-2">{{ error }}</p>

          <button
            type="submit"
            :disabled="loading"
            class="w-full py-2.5 bg-[#00C4CC] hover:bg-[#00AFBB] text-white font-semibold text-[13px] rounded-lg transition-colors disabled:opacity-60"
          >
            {{ loading ? 'Signing in…' : 'Sign In' }}
          </button>
        </form>
      </div>
    </NuxtLayout>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { useAuthStore } from '~/stores/auth'

definePageMeta({ layout: false })

const auth    = useAuthStore()
const form    = ref({ username: '', password: '' })
const loading = ref(false)
const error   = ref<string | null>(null)
const config  = useRuntimeConfig()

async function handleLogin() {
  loading.value = true
  error.value   = null
  try {
    const res = await fetch(`${config.public.apiBase}/auth/login`, {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      body:    JSON.stringify(form.value),
    })
    const json = await res.json()
    if (!res.ok) throw new Error(json.message ?? 'Login failed.')
    auth.setAuth(json.token, json.expires_at, json.user)
    navigateTo('/')
  } catch (e: unknown) {
    error.value = e instanceof Error ? e.message : 'Login failed.'
  } finally {
    loading.value = false
  }
}
</script>
