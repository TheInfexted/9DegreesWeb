<template>
  <NuxtLayout>
    <div class="max-w-2xl space-y-5">
      <!-- Company Info -->
      <div class="bg-white border border-[#E8E8EC] rounded-2xl p-5 shadow-sm">
        <h2 class="text-[14px] font-bold text-ink mb-4">Company Information</h2>
        <div class="grid grid-cols-2 gap-3">
          <div class="col-span-2"><label class="field-label">Company Name</label><input v-model="s.company_name" class="field-input w-full" /></div>
          <div class="col-span-2"><label class="field-label">Address</label><input v-model="s.company_address" class="field-input w-full" /></div>
          <div><label class="field-label">SSM Registration</label><input v-model="s.company_registration" class="field-input w-full" /></div>
          <div><label class="field-label">Phone</label><input v-model="s.company_phone" class="field-input w-full" /></div>
          <div class="col-span-2"><label class="field-label">Email</label><input v-model="s.company_email" type="email" class="field-input w-full" /></div>
        </div>
        <div class="mt-4 flex justify-end">
          <button class="btn-primary" :disabled="saveLoading" @click="saveSettings">{{ saveLoading ? 'Saving…' : 'Save Changes' }}</button>
        </div>
        <p v-if="saveMsg" class="mt-2 text-[12px] text-green-600 text-right">{{ saveMsg }}</p>
      </div>

      <!-- Change Password -->
      <div class="bg-white border border-[#E8E8EC] rounded-2xl p-5 shadow-sm">
        <h2 class="text-[14px] font-bold text-ink mb-4">Change Password</h2>
        <div class="space-y-3">
          <div><label class="field-label">Current Password</label><input v-model="pwd.current" type="password" class="field-input w-full" /></div>
          <div><label class="field-label">New Password</label><input v-model="pwd.new" type="password" class="field-input w-full" /></div>
        </div>
        <p v-if="pwdError" class="mt-2 text-[12px] text-red-500 bg-red-50 rounded-lg px-3 py-1.5">{{ pwdError }}</p>
        <div class="mt-4 flex justify-end">
          <button class="btn-primary" :disabled="pwdLoading" @click="changePassword">{{ pwdLoading ? 'Updating…' : 'Update Password' }}</button>
        </div>
        <p v-if="pwdMsg" class="mt-2 text-[12px] text-green-600 text-right">{{ pwdMsg }}</p>
      </div>
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
  saveLoading.value = false; saveMsg.value = 'Settings saved.'
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
    pwdMsg.value = 'Password updated.'; pwd.value = { current: '', new: '' }
    setTimeout(() => pwdMsg.value = '', 3000)
  } catch (e: unknown) {
    pwdError.value = e instanceof Error ? e.message : 'Failed.'
  } finally {
    pwdLoading.value = false
  }
}
</script>
