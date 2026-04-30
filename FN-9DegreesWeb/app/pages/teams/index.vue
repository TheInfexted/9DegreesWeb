<template>
  <NuxtLayout>
    <div class="mb-4 flex justify-end">
      <button class="btn-primary inline-flex items-center gap-1.5" @click="openCreate">
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14M5 12h14"/></svg>
        New team
      </button>
    </div>

    <AppTable :columns="columns" :rows="teams" :loading="loading">
      <template #default="{ row }">
        <td class="px-4 py-3 text-[13px] font-medium text-ink tracking-[-0.005em]">{{ row.name }}</td>
        <td class="px-4 py-3 text-[13px] text-text-soft">{{ row.leader?.name ?? '—' }}</td>
        <td class="px-4 py-3"><AppBadge :variant="row.status">{{ row.status }}</AppBadge></td>
        <td class="px-4 py-3">
          <div class="flex justify-end gap-1">
            <button class="act-btn" title="Edit" @click="openEdit(row)">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536M16.5 3.75a2.121 2.121 0 113 3L7 19.25l-4 1 1-4L16.5 3.75z"/></svg>
            </button>
            <button class="act-btn text-[#DC4438] hover:bg-[#FDF2F1]" title="Delete" @click="doDelete(row)">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M6 18L18 6"/></svg>
            </button>
          </div>
        </td>
      </template>
    </AppTable>

    <AppModal v-model="showForm" :title="editTeam ? 'Edit team' : 'New team'" size="sm">
      <div class="space-y-3">
        <div><label class="field-label">Team name</label><input v-model="form.name" class="field-input w-full" required /></div>
        <div v-if="editTeam">
          <label class="field-label">Assign leader</label>
          <AppSelect v-model="form.leader_id" :options="leaderOpts" placeholder="No leader" />
        </div>
        <p
          v-if="formError"
          class="text-[12px] text-[#B83227] bg-[#FDF2F1] ring-1 ring-inset ring-[#F1D8D5] rounded-md px-3 py-2"
        >{{ formError }}</p>
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
  { key: 'name',   label: 'Team name' },
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
  const ok = await confirm('Delete team', `Delete "${row.name}"?`)
  if (!ok) return
  await fetch(`${config.public.apiBase}/teams/${row.id}`, { method: 'DELETE', headers: { Authorization: `Bearer ${auth.token}` } })
  refresh()
}
</script>
