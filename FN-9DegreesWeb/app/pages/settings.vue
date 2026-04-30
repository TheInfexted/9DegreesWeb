<template>
  <NuxtLayout>
    <div class="max-w-2xl space-y-5">
      <!-- Company Info -->
      <section class="surface p-5 lg:p-6">
        <header class="mb-5">
          <h2 class="text-[15px] font-semibold text-ink tracking-tightest">Company information</h2>
          <p class="text-[12px] text-text-muted mt-0.5">Used on payslips and PDF exports.</p>
        </header>
        <div class="grid grid-cols-2 gap-3">
          <div class="col-span-2"><label class="field-label">Company name</label><input v-model="s.company_name" class="field-input w-full" /></div>
          <div class="col-span-2"><label class="field-label">Address</label><input v-model="s.company_address" class="field-input w-full" /></div>
          <div><label class="field-label">SSM registration</label><input v-model="s.company_registration" class="field-input w-full" /></div>
          <div><label class="field-label">Phone</label><input v-model="s.company_phone" class="field-input w-full" /></div>
          <div class="col-span-2"><label class="field-label">Email</label><input v-model="s.company_email" type="email" class="field-input w-full" /></div>
        </div>
        <div class="mt-5 flex items-center justify-between">
          <p
            v-if="saveMsg"
            class="text-[12px] text-[#1F7A3F] inline-flex items-center gap-1.5"
            role="status"
          >
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
            {{ saveMsg }}
          </p>
          <span v-else />
          <button class="btn-primary" :disabled="saveLoading" @click="saveSettings">{{ saveLoading ? 'Saving…' : 'Save changes' }}</button>
        </div>
      </section>

      <!-- Change Password -->
      <section class="surface p-5 lg:p-6">
        <header class="mb-5">
          <h2 class="text-[15px] font-semibold text-ink tracking-tightest">Change password</h2>
          <p class="text-[12px] text-text-muted mt-0.5">You'll stay signed in on this device after updating.</p>
        </header>
        <div class="space-y-3">
          <div><label class="field-label">Current password</label><input v-model="pwd.current" type="password" class="field-input w-full" autocomplete="current-password" /></div>
          <div><label class="field-label">New password</label><input v-model="pwd.new" type="password" class="field-input w-full" autocomplete="new-password" /></div>
        </div>
        <p
          v-if="pwdError"
          class="mt-3 text-[12px] text-[#B83227] bg-[#FDF2F1] ring-1 ring-inset ring-[#F1D8D5] rounded-md px-3 py-2 inline-flex items-start gap-2"
        >
          <svg class="w-3.5 h-3.5 mt-0.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3m0 4h.01M5.062 19h13.876c1.54 0 2.502-1.667 1.732-3L13.732 5c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.722 3z"/></svg>
          {{ pwdError }}
        </p>
        <div class="mt-5 flex items-center justify-between">
          <p
            v-if="pwdMsg"
            class="text-[12px] text-[#1F7A3F] inline-flex items-center gap-1.5"
            role="status"
          >
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
            {{ pwdMsg }}
          </p>
          <span v-else />
          <button class="btn-primary" :disabled="pwdLoading" @click="changePassword">{{ pwdLoading ? 'Updating…' : 'Update password' }}</button>
        </div>
      </section>
    </div>
  </NuxtLayout>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue'

definePageMeta({ middleware: 'auth' })

const { data: settingsData } = useAPI('settings')
const config  = useRuntimeConfig()
const auth    = useAuthStore()
const s       = ref({ company_name: '', company_address: '', company_registration: '', company_phone: '', company_email: '' })
const pwd     = ref({ current: '', new: '' })
const saveLoading = ref(false)
const pwdLoading  = ref(false)
const saveMsg     = ref('')
const pwdError    = ref('')
const pwdMsg      = ref('')

watch(settingsData, (v: any) => { if (v) Object.assign(s.value, v) }, { immediate: true })

async function saveSettings() {
  saveLoading.value = true; saveMsg.value = ''
  await fetch(`${config.public.apiBase}/settings`, {
    method: 'PUT', headers: { 'Content-Type': 'application/json', Authorization: `Bearer ${auth.token}` }, body: JSON.stringify(s.value),
  })
  saveLoading.value = false; saveMsg.value = 'Settings saved'
  setTimeout(() => saveMsg.value = '', 3000)
}

async function changePassword() {
  pwdLoading.value = true; pwdError.value = ''; pwdMsg.value = ''
  try {
    const res  = await fetch(`${config.public.apiBase}/settings/password`, {
      method: 'PUT', headers: { 'Content-Type': 'application/json', Authorization: `Bearer ${auth.token}` },
      body: JSON.stringify({ current_password: pwd.value.current, new_password: pwd.value.new }),
    })
    const json = await res.json()
    if (!res.ok) throw new Error(json.message ?? 'Failed.')
    pwdMsg.value = 'Password updated'; pwd.value = { current: '', new: '' }
    setTimeout(() => pwdMsg.value = '', 3000)
  } catch (e: unknown) {
    pwdError.value = e instanceof Error ? e.message : 'Failed.'
  } finally {
    pwdLoading.value = false
  }
}
</script>
