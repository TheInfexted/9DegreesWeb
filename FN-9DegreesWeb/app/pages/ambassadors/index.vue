<template>
  <NuxtLayout>
    <div class="bg-white border border-[#E8E8EC] rounded-2xl p-4 mb-4 shadow-sm">
      <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
        <div class="flex-1 min-w-0 max-w-xl space-y-1.5">
          <label class="field-label" for="amb-search">Search</label>
          <input
            id="amb-search"
            v-model="searchDraft"
            type="search"
            class="field-input w-full"
            placeholder="Name, full name, IC, role, team…"
            autocomplete="off"
          />
        </div>
        <button type="button" class="btn-primary w-full sm:w-auto shrink-0" @click="openCreate">+ New Ambassador</button>
      </div>
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

    <div
      v-if="meta"
      class="bg-white border border-[#E8E8EC] rounded-2xl p-4 mt-4 shadow-sm flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between"
    >
      <p class="text-[13px] text-gray-500 lg:pt-1">
        <template v-if="meta.total === 0">
          {{ String(listParams.q ?? '').trim() ? 'No ambassadors match this search.' : 'No ambassadors to show.' }}
        </template>
        <template v-else>
          Showing <span class="font-medium text-ink">{{ rangeStart }}</span>–<span class="font-medium text-ink">{{ rangeEnd }}</span>
          of <span class="font-medium text-ink">{{ meta.total }}</span>
        </template>
      </p>
      <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-end sm:justify-end">
        <div class="w-full sm:w-40">
          <AppSelect v-model="perPageSelect" :options="perPageOpts" aria-label="Rows per page" />
        </div>
        <div class="flex flex-wrap items-center gap-2">
          <button
            type="button"
            class="btn-secondary text-[13px] px-3 py-1.5 min-w-[5.5rem]"
            :disabled="!meta.total || meta.page <= 1"
            @click="goPage(meta.page - 1)"
          >
            Previous
          </button>
          <span class="text-[13px] text-gray-600 px-1 tabular-nums">
            Page {{ meta.page }} of {{ meta.last_page }}
          </span>
          <button
            type="button"
            class="btn-secondary text-[13px] px-3 py-1.5 min-w-[5.5rem]"
            :disabled="!meta.total || meta.page >= meta.last_page"
            @click="goPage(meta.page + 1)"
          >
            Next
          </button>
        </div>
      </div>
    </div>

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
import { ref, computed, watch } from 'vue'

definePageMeta({ middleware: 'auth' })

const showForm       = ref(false)
const editAmbassador = ref<any>(null)
const formError      = ref<string | null>(null)
const formLoading    = ref(false)

const defaultForm = () => ({ name: '', full_name: '', ic: '', role_id: '', team_id: '', custom_commission_rate: 10, kpi: '', commission_increase: '', use_kpi_bonus: false, bank_name: '', bank_account_number: '', bank_owner_name: '' })
const form = ref(defaultForm())

/** DB / JSON often sends tinyint as "0" / "1"; `!!"0"` is wrongly true in JS. */
function dbIntFlagIsOn(v: unknown): boolean {
  return v === 1 || v === true || v === '1'
}

function kpiBonusFieldsFilled(row: { kpi?: unknown; commission_increase?: unknown }): boolean {
  const k = row.kpi
  const c = row.commission_increase
  if (k === null || k === undefined || k === '') return false
  if (c === null || c === undefined || c === '') return false
  return true
}

function effectiveUseKpiBonus(checked: boolean, row: { kpi?: unknown; commission_increase?: unknown }): boolean {
  return checked && kpiBonusFieldsFilled(row)
}

const listParams = ref<Record<string, unknown>>({ page: 1, per_page: 15, q: '' })
const searchDraft = ref('')
let searchDebounce: ReturnType<typeof setTimeout> | undefined
watch(searchDraft, (v) => {
  if (searchDebounce) clearTimeout(searchDebounce)
  searchDebounce = setTimeout(() => {
    const t   = (v ?? '').trim()
    const cur = String(listParams.value.q ?? '').trim()
    if (t === cur) return
    listParams.value = { ...listParams.value, q: t, page: 1 }
  }, 320)
})

const { data: ambassadors, loading, meta, refresh } = useAPI('ambassadors', listParams)
const { data: roles }  = useAPI('roles')
const { data: teams }  = useAPI('teams')

const perPageOpts = [
  { value: 15, label: '15 per page' },
  { value: 25, label: '25 per page' },
  { value: 50, label: '50 per page' },
]

const perPageSelect = computed({
  get() {
    return listParams.value.per_page ?? 15
  },
  set(v: string | number) {
    const n = Number(v)
    listParams.value = {
      ...listParams.value,
      per_page: Number.isFinite(n) && n > 0 ? n : 15,
      page: 1,
    }
  },
})

const rangeStart = computed(() => {
  const m = meta.value
  if (!m?.total) return 0
  return (m.page - 1) * m.per_page + 1
})

const rangeEnd = computed(() => {
  const m = meta.value
  if (!m?.total) return 0
  return Math.min(m.page * m.per_page, m.total)
})

function goPage(p: number) {
  const m = meta.value
  if (!m) return
  const next = Math.max(1, Math.min(p, m.last_page))
  listParams.value = { ...listParams.value, page: next }
}
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
  const merged = { ...defaultForm(), ...row }
  form.value = {
    ...merged,
    use_kpi_bonus: effectiveUseKpiBonus(dbIntFlagIsOn(row.use_kpi_bonus), merged),
  }
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
      body: JSON.stringify({
        ...form.value,
        use_kpi_bonus: effectiveUseKpiBonus(form.value.use_kpi_bonus, form.value) ? 1 : 0,
      }),
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
