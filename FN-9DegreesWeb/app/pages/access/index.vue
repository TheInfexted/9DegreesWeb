<template>
  <NuxtLayout>
    <div class="mb-4 flex justify-end">
      <button class="btn-primary" @click="openCreate">+ New Account</button>
    </div>

    <AppTable :columns="columns" :rows="accounts" :loading="loading">
      <template #default="{ row }">
        <td class="px-4 py-3 text-[13px] font-medium text-ink font-mono">{{ row.username }}</td>
        <td class="px-4 py-3 text-[13px] text-gray-500">{{ row.ambassador_name ?? '—' }}</td>
        <td class="px-4 py-3"><AppBadge :variant="row.role">{{ row.role }}</AppBadge></td>
        <td class="px-4 py-3"><AppBadge :variant="row.is_active ? 'active' : 'inactive'">{{ row.is_active ? 'Active' : 'Inactive' }}</AppBadge></td>
        <td class="px-4 py-3">
          <div class="flex justify-end gap-1">
            <button v-if="row.role !== 'owner'" class="act-btn" @click="openEdit(row)">✎</button>
            <button v-if="row.role !== 'owner' && row.is_active" class="act-btn text-red-400" @click="doDeactivate(row)">✕</button>
          </div>
        </td>
      </template>
    </AppTable>

    <AppModal v-model="showForm" :title="editAccount ? 'Edit Account' : 'New Account'" size="md">
      <div class="space-y-3">
        <div v-if="!editAccount"><label class="field-label">Username</label><input v-model="form.username" class="field-input w-full" /></div>
        <div v-if="!editAccount"><label class="field-label">Password</label><input v-model="form.password" type="password" class="field-input w-full" /></div>
        <div><label class="field-label">Role</label>
          <AppSelect v-model="form.role" :options="roleOpts" />
        </div>
        <div><label class="field-label">Link to Ambassador (optional)</label>
          <AppSelect v-model="form.ambassador_id" :options="ambassadorOpts" placeholder="No ambassador" />
        </div>
        <p v-if="formError" class="text-[12px] text-red-500 bg-red-50 rounded-lg px-3 py-2">{{ formError }}</p>
      </div>
      <template #footer>
        <button class="btn-secondary" @click="showForm = false">Cancel</button>
        <button class="btn-primary" :disabled="formLoading" @click="handleSave">{{ formLoading ? 'Saving…' : 'Save' }}</button>
      </template>
    </AppModal>
  </NuxtLayout>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'

definePageMeta({ middleware: 'auth' })

const showForm    = ref(false)
const editAccount = ref<any>(null)
const formError   = ref<string | null>(null)
const formLoading = ref(false)
const form        = ref({ username: '', password: '', role: 'ambassador', ambassador_id: '' })

const { data: accounts, loading, refresh } = useAPI('access')
const { data: ambassadors }               = useAPI('ambassadors', { status: 'active' })
const config = useRuntimeConfig(); const auth = useAuthStore()

const roleOpts       = [{ value: 'admin', label: 'Admin' }, { value: 'leader', label: 'Leader' }, { value: 'ambassador', label: 'Ambassador' }]
const ambassadorOpts = computed(() => (ambassadors.value ?? []).map((a: any) => ({ value: a.id, label: a.name })))

const columns = [
  { key: 'username',   label: 'Username'    },
  { key: 'ambassador', label: 'Ambassador'  },
  { key: 'role',       label: 'Role'        },
  { key: 'status',     label: 'Status'      },
  { key: 'actions',    label: ''            },
]

function openCreate() { editAccount.value = null; form.value = { username: '', password: '', role: 'ambassador', ambassador_id: '' }; showForm.value = true }
function openEdit(row: any) { editAccount.value = row; form.value = { username: row.username, password: '', role: row.role, ambassador_id: row.ambassador_id ?? '' }; showForm.value = true }

async function handleSave() {
  formLoading.value = true; formError.value = null
  try {
    const url    = editAccount.value ? `${config.public.apiBase}/access/${editAccount.value.id}` : `${config.public.apiBase}/access`
    const method = editAccount.value ? 'PUT' : 'POST'
    const res    = await fetch(url, { method, headers: { 'Content-Type': 'application/json', Authorization: `Bearer ${auth.token}` }, body: JSON.stringify(form.value) })
    const json   = await res.json()
    if (!res.ok) throw new Error(json.message ?? 'Failed.')
    showForm.value = false; refresh()
  } catch (e: unknown) {
    formError.value = e instanceof Error ? e.message : 'Failed.'
  } finally {
    formLoading.value = false
  }
}

async function doDeactivate(row: any) {
  const { confirm } = useConfirm()
  const ok = await confirm('Deactivate Account', `Deactivate account "${row.username}"?`)
  if (!ok) return
  await fetch(`${config.public.apiBase}/access/${row.id}`, { method: 'DELETE', headers: { Authorization: `Bearer ${auth.token}` } })
  refresh()
}
</script>
