<template>
  <div>
    <NuxtLayout name="auth">
      <div class="w-full max-w-[400px] surface shadow-pop p-8 sm:p-9 relative overflow-hidden">
        <!-- corner accent -->
        <div
          class="absolute -top-16 -right-16 w-48 h-48 rounded-full pointer-events-none"
          style="background: radial-gradient(closest-side, rgba(0,181,189,0.10), transparent 70%)"
          aria-hidden="true"
        />

        <div class="relative">
          <!-- Brand mark -->
          <div class="flex items-center gap-3 mb-8">
            <div class="w-12 h-12 rounded-xl bg-ink overflow-hidden flex items-center justify-center shadow-soft">
              <img
                src="~/assets/img/9degree.png"
                alt=""
                class="h-full w-full object-contain origin-center scale-[2.1] select-none pointer-events-none"
              />
            </div>
            <div class="flex flex-col leading-tight">
              <span class="text-[13px] font-semibold text-ink tracking-[-0.005em]">9 Degrees</span>
              <span class="text-[10.5px] text-text-muted uppercase tracking-[0.1em]">Sales tracking</span>
            </div>
          </div>

          <!-- Headline -->
          <h1 class="text-[26px] font-semibold text-ink tracking-tightest leading-[1.1] mb-1.5">
            Welcome back.
          </h1>
          <p class="text-[13px] text-text-soft mb-7">
            Sign in to keep tracking commissions.
          </p>

          <form class="space-y-3.5" @submit.prevent="handleLogin">
            <div>
              <label class="field-label" for="login-username">Username</label>
              <input
                id="login-username"
                v-model="form.username"
                type="text"
                autocomplete="username"
                required
                class="field-input w-full py-2.5"
                placeholder="Your username"
              />
            </div>
            <div>
              <label class="field-label" for="login-password">Password</label>
              <input
                id="login-password"
                v-model="form.password"
                type="password"
                autocomplete="current-password"
                required
                class="field-input w-full py-2.5"
                placeholder="Your password"
              />
            </div>

            <p
              v-if="error"
              class="text-[12px] text-[#B83227] bg-[#FDF2F1] ring-1 ring-inset ring-[#F1D8D5] rounded-md px-3 py-2 flex items-start gap-2"
            >
              <svg class="w-3.5 h-3.5 mt-0.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3m0 4h.01M5.062 19h13.876c1.54 0 2.502-1.667 1.732-3L13.732 5c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.722 3z"/>
              </svg>
              {{ error }}
            </p>

            <button
              type="submit"
              :disabled="loading"
              class="btn-primary w-full py-2.5 mt-1 inline-flex items-center justify-center gap-2"
            >
              <span v-if="loading" class="w-3.5 h-3.5 rounded-full border-2 border-white/30 border-t-white animate-spin" aria-hidden="true" />
              {{ loading ? 'Signing in' : 'Sign in' }}
            </button>
          </form>
        </div>
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
    if (!res.ok) throw new Error(json.message ?? 'Sign-in failed.')
    auth.setAuth(json.token, json.expires_at, json.user)
    navigateTo('/')
  } catch (e: unknown) {
    error.value = e instanceof Error ? e.message : 'Sign-in failed.'
  } finally {
    loading.value = false
  }
}
</script>
