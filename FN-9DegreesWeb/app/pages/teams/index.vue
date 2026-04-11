<template>
  <NuxtLayout>
    <div class="mb-4 flex justify-end">
      <button class="btn-primary" @click="openCreate">+ New Team</button>
    </div>

    <AppTable :columns="columns" :rows="teams" :loading="loading">
      <template #default="{ row }">
        <td class="px-4 py-3 text-[13px] font-medium text-ink">{{ row.name }}</td>
        <td class="px-4 py-3 text-[13px] text-gray-500">{{ row.leader?.name ?? '—' }}</td>
        <td class="px-4 py-3"><AppBadge :variant="row.status">{{ row.status }}</AppBadge></td>
        <td class="px-4 py-3">
          <div class="flex justify-end gap-1">
            <button class="act-btn" @click="openEdit(row)">✎</button>
            <button class="act-btn text-red-400" @click="doDelete(row)">✕</button>
          </div>
        </td>
      </template>
    </AppTable>

    <AppModal v-model="showForm" :title="editTeam ? 'Edit Team' : 'New Team'" size="sm">
      <div class="space-y-3">
        <div><label class="field-label">Team Name</label><input v-model="form.name" class="field-input w-full" required /></div>
        <div v-if="editTeam">
          <label class="field-label">Assign Leader</label>
          <AppSelect v-model="form.leader_id" :options="leaderOpts" placeholder="No leader" />
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

const showForm   = ref(false)
const editTeam   = ref<any>(null)
const formError  = ref<string | null>(null)
const formLoading = ref(false)
const form       = ref({ name: '', leader_id: '' })

const { data: teams, loading, refresh } = useAPI('teams')
const { data: ambassadors }             = useAPI('ambassadors', { status: 'active' })
const config = useRuntimeConfig(); const auth = useAuthStore()

const leaderOpts = computed(() =>
  (ambassadors.value ?? [])
    .filter((a: any) => a.role_name === 'Leader')
    .map((a: any) => ({ value: a.id, label: a.name }))
)

const columns = [
  { key: 'name',   label: 'Team Name' },
  { key: 'leader', label: 'Leader'    },
  { key: 'status', label: 'Status'    },
  { key: 'actions',label: ''          },
]

function openCreate() { editTeam.value = null; form.value = { name: '', leader_id: '' }; showForm.value = true }
function openEdit(row: any) { editTeam.value = row; form.value = { name: row.name, leader_id: row.leader?.id ?? '' }; showForm.value = true }

async function handleSave() {
  formLoading.value = true; formError.value = null
  try {
    const url    = editTeam.value ? `${config.public.apiBase}/teams/${editTeam.value.id}` : `${config.public.apiBase}/teams`
    const method = editTeam.value ? 'PUT' : 'POST'
    const res    = await fetch(url, { method, headers: { 'Content-Type': 'application/json', Authorization: `Bearer ${auth.token}` }, body: JSON.stringify({ name: form.value.name }) })
    const json   = await res.json()
    if (!res.ok) throw new Error(json.message ?? 'Failed.')

    // Assign leader if set
    if (editTeam.value && form.value.leader_id) {
      await fetch(`${config.public.apiBase}/teams/${editTeam.value.id}/leader`, {
        method: 'PUT', headers: { 'Content-Type': 'application/json', Authorization: `Bearer ${auth.token}` },
        body: JSON.stringify({ ambassador_id: form.value.leader_id }),
      })
    }
    showForm.value = false; refresh()
  } catch (e: unknown) {
    formError.value = e instanceof Error ? e.message : 'Failed.'
  } finally {
    formLoading.value = false
  }
}

async function doDelete(row: any) {
  const { confirm } = useConfirm()
  const ok = await confirm('Delete Team', `Delete "${row.name}"?`)
  if (!ok) return
  await fetch(`${config.public.apiBase}/teams/${row.id}`, { method: 'DELETE', headers: { Authorization: `Bearer ${auth.token}` } })
  refresh()
}
</script>
