<template>
  <NuxtLayout>
    <div class="mb-4 flex justify-end">
      <button class="btn-primary" @click="openCreate">+ New Ambassador</button>
    </div>

    <AppTable :columns="columns" :rows="ambassadors" :loading="loading">
      <template #default="{ row }">
        <td class="px-4 py-3 text-[13px] font-medium text-ink">{{ row.name }}</td>
        <td class="px-4 py-3 text-[13px] text-gray-500">{{ row.role_name }}</td>
        <td class="px-4 py-3 text-[13px] text-gray-500">{{ row.team_name ?? '—' }}</td>
        <td class="px-4 py-3 text-[13px] text-right text-[#00A0A6]">{{ row.custom_commission_rate }}%</td>
        <td class="px-4 py-3"><AppBadge :variant="row.status">{{ row.status }}</AppBadge></td>
        <td class="px-4 py-3">
          <div class="flex justify-end gap-1">
            <button class="act-btn" @click="openEdit(row)">✎</button>
            <button v-if="!['Johnny','Unassigned Sales'].includes(row.name)" class="act-btn text-red-400" @click="doDeactivate(row)">✕</button>
          </div>
        </td>
      </template>
    </AppTable>

    <!-- Create/Edit Modal -->
    <AppModal v-model="showForm" :title="editAmbassador ? 'Edit Ambassador' : 'New Ambassador'" size="lg">
      <form class="grid grid-cols-2 gap-3" @submit.prevent="handleSave">
        <div><label class="field-label">Name (Alias)</label><input v-model="form.name" class="field-input w-full" required /></div>
        <div><label class="field-label">Full Legal Name</label><input v-model="form.full_name" class="field-input w-full" /></div>
        <div><label class="field-label">IC / Passport</label><input v-model="form.ic" class="field-input w-full" /></div>
        <div><label class="field-label">Commission Role</label><AppSelect v-model="form.role_id" :options="roleOpts" /></div>
        <div><label class="field-label">Team</label><AppSelect v-model="form.team_id" :options="teamOpts" placeholder="No team" /></div>
        <div><label class="field-label">Commission Rate (%)</label><input v-model="form.custom_commission_rate" type="number" step="0.01" min="0" max="12" class="field-input w-full" /></div>
        <div><label class="field-label">KPI Target (RM)</label><input v-model="form.kpi" type="number" step="0.01" class="field-input w-full" placeholder="Optional" /></div>
        <div><label class="field-label">KPI Bonus (%)</label><input v-model="form.commission_increase" type="number" step="0.01" class="field-input w-full" placeholder="Optional" /></div>
        <div class="col-span-2 flex items-center gap-2">
          <input v-model="form.use_kpi_bonus" type="checkbox" id="use_kpi" class="accent-[#00C4CC]" />
          <label for="use_kpi" class="text-[13px] text-gray-600">Enable KPI bonus</label>
        </div>
        <div class="col-span-2 border-t border-[#F0F0F0] pt-3 mt-1">
          <p class="text-[11px] font-bold uppercase tracking-wide text-gray-400 mb-2">Bank Details</p>
          <div class="grid grid-cols-2 gap-3">
            <div><label class="field-label">Bank Name</label><input v-model="form.bank_name" class="field-input w-full" /></div>
            <div><label class="field-label">Account Number</label><input v-model="form.bank_account_number" class="field-input w-full" /></div>
            <div class="col-span-2"><label class="field-label">Account Holder Name</label><input v-model="form.bank_owner_name" class="field-input w-full" /></div>
          </div>
        </div>
        <p v-if="formError" class="col-span-2 text-[12px] text-red-500 bg-red-50 rounded-lg px-3 py-2">{{ formError }}</p>
      </form>
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

const showForm       = ref(false)
const editAmbassador = ref<any>(null)
const formError      = ref<string | null>(null)
const formLoading    = ref(false)

const defaultForm = () => ({ name: '', full_name: '', ic: '', role_id: '', team_id: '', custom_commission_rate: 10, kpi: '', commission_increase: '', use_kpi_bonus: false, bank_name: '', bank_account_number: '', bank_owner_name: '' })
const form = ref(defaultForm())

const { data: ambassadors, loading, refresh } = useAPI('ambassadors')
const { data: roles }  = useAPI('roles')
const { data: teams }  = useAPI('teams')
const config           = useRuntimeConfig()
const auth             = useAuthStore()

const roleOpts = computed(() => (roles.value ?? []).map((r: any) => ({ value: r.id, label: r.name })))
const teamOpts = computed(() => (teams.value ?? []).map((t: any) => ({ value: t.id, label: t.name })))

const columns = [
  { key: 'name',   label: 'Name'       },
  { key: 'role',   label: 'Role'       },
  { key: 'team',   label: 'Team'       },
  { key: 'rate',   label: 'Rate', align: 'right' as const },
  { key: 'status', label: 'Status'     },
  { key: 'actions',label: ''           },
]

function openCreate() { editAmbassador.value = null; form.value = defaultForm(); showForm.value = true }
function openEdit(row: any) {
  editAmbassador.value = row
  form.value = { ...defaultForm(), ...row, use_kpi_bonus: !!row.use_kpi_bonus }
  showForm.value = true
}

async function handleSave() {
  formLoading.value = true; formError.value = null
  try {
    const url    = editAmbassador.value ? `${config.public.apiBase}/ambassadors/${editAmbassador.value.id}` : `${config.public.apiBase}/ambassadors`
    const method = editAmbassador.value ? 'PUT' : 'POST'
    const res    = await fetch(url, {
      method,
      headers: { 'Content-Type': 'application/json', Authorization: `Bearer ${auth.token}` },
      body: JSON.stringify({ ...form.value, use_kpi_bonus: form.value.use_kpi_bonus ? 1 : 0 }),
    })
    const json = await res.json()
    if (!res.ok) throw new Error(json.message ?? 'Failed to save.')
    showForm.value = false; refresh()
  } catch (e: unknown) {
    formError.value = e instanceof Error ? e.message : 'Failed.'
  } finally {
    formLoading.value = false
  }
}

async function doDeactivate(row: any) {
  const { confirm } = useConfirm()
  const ok = await confirm('Deactivate Ambassador', `Deactivate ${row.name}?`)
  if (!ok) return
  await fetch(`${config.public.apiBase}/ambassadors/${row.id}`, { method: 'DELETE', headers: { Authorization: `Bearer ${auth.token}` } })
  refresh()
}
</script>
