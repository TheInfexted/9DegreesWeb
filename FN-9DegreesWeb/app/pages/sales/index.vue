<template>
  <NuxtLayout>
    <!-- Filters -->
    <div class="surface p-4 mb-4">
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
        <AppSelect v-model="listParams.status" :options="statusOpts" placeholder="All statuses" />
        <AppSelect v-model="listParams.sale_type" :options="typeOpts" placeholder="All types" />
        <AppSelect v-model="listParams.ambassador_id" :options="ambassadorOpts" placeholder="All ambassadors" />
        <AppSelect v-model="listParams.month" :options="monthOpts" placeholder="All months" />
      </div>
      <div class="mt-3.5 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between border-t border-border-soft pt-3.5">
        <p class="text-[12px] text-text-soft">
          Confirm all applies to every <span class="font-medium text-ink">draft</span> row matching ambassador and month.
        </p>
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center shrink-0">
          <NuxtLink to="/sales/import" class="btn-secondary text-[13px] text-center inline-flex items-center justify-center gap-1.5">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
              <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3"/>
            </svg>
            Import from PDF
          </NuxtLink>
          <button
            type="button"
            class="btn-primary text-[13px]"
            :disabled="confirmAllLoading"
            @click="doConfirmAll"
          >
            {{ confirmAllLoading ? 'Confirming…' : 'Confirm all draft sales' }}
          </button>
        </div>
      </div>
    </div>

    <p class="text-[11.5px] text-text-muted mb-2">{{ summaryScopeLabel }}</p>
    <div v-if="salesSummaryCards" class="grid grid-cols-2 gap-3 mb-4">
      <AppCard label="Matching sales" :value="salesSummaryCards.count" :accent="false" />
      <AppCard label="Gross total" prefix="RM " :value="fmtSummaryGross" />
    </div>

    <!-- Table -->
    <AppTable :columns="columns" :rows="sales" :loading="loading">
      <template #default="{ row }">
        <td class="px-4 py-3 text-[13px] text-ink font-medium tracking-[-0.005em]">{{ row.ambassador_name }}</td>
        <td class="px-4 py-3 text-[13px] text-text-soft tabular">{{ formatDate(row.date) }}</td>
        <td class="px-4 py-3 text-[13px] text-text-soft">{{ row.sale_type }}</td>
        <td class="px-4 py-3 text-[13px] text-text-soft tabular">{{ row.table_number ?? '—' }}</td>
        <td class="px-4 py-3 text-[13px] text-right font-medium text-ink tabular">{{ formatRM(row.gross_amount) }}</td>
        <td class="px-4 py-3"><AppBadge :variant="row.status">{{ row.status }}</AppBadge></td>
        <td class="px-4 py-3">
          <div class="flex justify-end gap-1">
            <button class="act-btn" title="Edit" @click="openEdit(row)">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536M16.5 3.75a2.121 2.121 0 113 3L7 19.25l-4 1 1-4L16.5 3.75z"/></svg>
            </button>
            <button v-if="row.status === 'draft'" class="act-btn text-cyan-dark hover:bg-cyan-tint" title="Confirm" @click="doConfirm(row)">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
            </button>
            <button v-if="row.status !== 'void'" class="act-btn text-[#DC4438] hover:bg-[#FDF2F1]" title="Void" @click="doVoid(row)">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728L5.636 5.636m12.728 12.728A9 9 0 015.636 5.636"/></svg>
            </button>
            <button v-if="row.status === 'draft'" class="act-btn text-[#DC4438] hover:bg-[#FDF2F1]" title="Delete" @click="doDelete(row)">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M6 18L18 6"/></svg>
            </button>
          </div>
        </td>
      </template>
    </AppTable>

    <div
      v-if="meta"
      class="surface p-4 mt-4 flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between"
    >
      <p class="text-[13px] text-text-soft lg:pt-1 tabular">
        <template v-if="meta.total === 0">No sales match these filters.</template>
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
          <span class="text-[13px] text-text-soft px-1 tabular">
            Page <span class="text-ink font-medium">{{ meta.page }}</span> of <span class="text-ink font-medium">{{ meta.last_page }}</span>
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

    <!-- New Sale FAB (mobile) -->
    <button
      class="fixed bottom-5 right-5 lg:hidden w-12 h-12 bg-cyan hover:bg-cyan-dark active:scale-95 text-white rounded-full shadow-pop text-xl flex items-center justify-center transition-all duration-150"
      aria-label="New sale"
      @click="openCreate"
    >
      <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14M5 12h14"/></svg>
    </button>

    <!-- New Sale button (desktop) -->
    <div class="fixed bottom-6 right-6 hidden lg:block">
      <button class="btn-primary shadow-pop inline-flex items-center gap-1.5" @click="openCreate">
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14M5 12h14"/></svg>
        New sale
      </button>
    </div>

    <SaleFormModal
      v-model="showForm"
      :sale="editSale"
      :ambassadors="ambassadors ?? []"
      :default-create-date="defaultCreateSaleDate"
      @saved="refresh"
    />
  </NuxtLayout>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { formatDate } from '~/utils/dateFormat'
import { formatRM } from '~/utils/currency'

definePageMeta({ middleware: 'auth' })

const NO_SALES_SUMMARY_FILTERS: Record<string, unknown> = {}

const listParams = ref({
  status: '',
  sale_type: '',
  ambassador_id: '',
  month: '',
  page: 1,
  per_page: 25,
})

watch(
  () =>
    [
      listParams.value.status,
      listParams.value.sale_type,
      listParams.value.ambassador_id,
      listParams.value.month,
    ].join('\0'),
  () => {
    listParams.value = { ...listParams.value, page: 1 }
  },
)

const salesParams = computed(() => {
  const p = listParams.value
  const o: Record<string, unknown> = { page: p.page, per_page: p.per_page }
  if (p.status) o.status = p.status
  if (p.sale_type) o.sale_type = p.sale_type
  if (p.ambassador_id) o.ambassador_id = p.ambassador_id
  if (p.month) o.month = p.month
  return o
})

const salesSummaryParams = computed(() => {
  const p = listParams.value
  if (!p.status && !p.sale_type && !p.ambassador_id && !p.month) return NO_SALES_SUMMARY_FILTERS
  const o: Record<string, unknown> = {}
  if (p.status) o.status = p.status
  if (p.sale_type) o.sale_type = p.sale_type
  if (p.ambassador_id) o.ambassador_id = p.ambassador_id
  if (p.month) o.month = p.month
  return o
})

const { data: sales, loading, meta, refresh: refreshSalesList } = useAPI('sales', salesParams)
const { data: salesSummaryRaw, refresh: refreshSalesSummary } = useAPI('sales/summary', salesSummaryParams)
const { data: ambassadors }                    = useAPI('ambassadors', { status: 'active' })
const { data: months }                         = useAPI('sales/months')
const { data: latestDefaultsRaw, refresh: refreshLatestDefaults } = useAPI<Record<string, unknown>>('sales/latest-defaults')

const latestSaleDefaults = computed(() => {
  const v = latestDefaultsRaw.value
  if (!v || typeof v !== 'object') return null
  const row = 'date' in v && typeof (v as { date?: unknown }).date === 'string' ? (v as { date: string; ambassador_id?: unknown; team_id?: unknown }) : null
  if (row && /^\d{4}-\d{2}-\d{2}$/.test(row.date)) return row
  const inner = (v as { data?: unknown }).data
  if (inner && typeof inner === 'object' && typeof (inner as { date?: unknown }).date === 'string') {
    const d = (inner as { date: string }).date
    if (/^\d{4}-\d{2}-\d{2}$/.test(d)) return inner as { date: string; ambassador_id?: unknown; team_id?: unknown }
  }
  return null
})

const defaultCreateSaleDate = computed(() => latestSaleDefaults.value?.date ?? null)

const showForm = ref(false)
const editSale = ref<any>(null)
const confirmAllLoading = ref(false)

const statusOpts     = [{ value: 'draft', label: 'Draft' }, { value: 'confirmed', label: 'Confirmed' }, { value: 'void', label: 'Void' }]
const typeOpts       = [{ value: 'Table', label: 'Table' }, { value: 'BGO', label: 'BGO' }]
const ambassadorOpts = computed(() => (ambassadors.value ?? []).map((a: any) => ({ value: a.id, label: a.name })))
const monthOpts      = computed(() => (months.value ?? []).map((m: any) => ({ value: m.month, label: m.month })))

const salesSummaryCards = computed(() => {
  const s = salesSummaryRaw.value as { count?: number; gross_total?: number } | null
  if (s == null || typeof s !== 'object') return null
  return {
    count:       Number(s.count ?? 0),
    gross_total: Number(s.gross_total ?? 0),
  }
})

const summaryScopeLabel = computed(() => {
  const p = listParams.value
  const filtered = !!(p.status || p.sale_type || p.ambassador_id || p.month)
  return filtered
    ? 'Summary totals for the current filters (all matching rows, not just this page).'
    : 'Summary totals across all sales (no filters applied).'
})

const fmtSummaryGross = computed(() =>
  (salesSummaryCards.value?.gross_total ?? 0).toLocaleString('en-MY', { minimumFractionDigits: 2, maximumFractionDigits: 2 }),
)

async function refresh() {
  await Promise.all([refreshSalesList(), refreshSalesSummary(), refreshLatestDefaults()])
}

const perPageOpts = [
  { value: 15, label: '15 per page' },
  { value: 25, label: '25 per page' },
  { value: 50, label: '50 per page' },
]

const perPageSelect = computed({
  get() {
    return listParams.value.per_page ?? 25
  },
  set(v: string | number) {
    const n = Number(v)
    listParams.value = {
      ...listParams.value,
      per_page: Number.isFinite(n) && n > 0 ? n : 25,
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

const columns = [
  { key: 'ambassador', label: 'Ambassador' },
  { key: 'date',       label: 'Date'       },
  { key: 'type',       label: 'Type'       },
  { key: 'table',      label: 'Table #'    },
  { key: 'gross',      label: 'Gross (RM)', align: 'right' as const },
  { key: 'status',     label: 'Status'     },
  { key: 'actions',    label: ''           },
]

const { confirm } = useConfirm()

async function openCreate() {
  editSale.value = null
  await refreshLatestDefaults()
  showForm.value = true
}
function openEdit(row: any) { editSale.value = row; showForm.value = true }

async function doConfirm(row: any) {
  const ok = await confirm('Confirm sale', `Confirm this sale for ${row.ambassador_name}?`)
  if (!ok) return
  const config = useRuntimeConfig(); const auth = useAuthStore()
  await fetch(`${config.public.apiBase}/sales/${row.id}/confirm`, { method: 'POST', headers: { Authorization: `Bearer ${auth.token}` } })
  await refresh()
}

async function doConfirmAll() {
  const ok = await confirm(
    'Confirm all draft sales',
    'This will confirm every draft sale that matches the current ambassador and month filters (all sale types: Table and BGO). Status and sale type filters on the list do not apply. Continue?',
  )
  if (!ok) return

  const config = useRuntimeConfig()
  const auth   = useAuthStore()
  const p      = listParams.value
  const body: Record<string, unknown> = {}
  if (p.ambassador_id) body.ambassador_id = p.ambassador_id
  if (p.month) body.month = p.month

  confirmAllLoading.value = true
  try {
    const res  = await fetch(`${config.public.apiBase}/sales/confirm-drafts`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Authorization: `Bearer ${auth.token}` },
      body: JSON.stringify(body),
    })
    const json = await res.json()
    if (!res.ok) throw new Error(json.message ?? 'Request failed.')
    const confirmed = json.data?.confirmed ?? 0
    const failed    = json.data?.failed ?? []
    let msg         = `Confirmed ${confirmed} sale(s).`
    if (failed.length) msg += ` ${failed.length} failed (see server logs).`
    window.alert(msg)
    await refresh()
  } catch (e: unknown) {
    window.alert(e instanceof Error ? e.message : 'Failed.')
  } finally {
    confirmAllLoading.value = false
  }
}

async function doVoid(row: any) {
  const ok = await confirm('Void sale', 'This action cannot be undone. Void this sale?')
  if (!ok) return
  const config = useRuntimeConfig(); const auth = useAuthStore()
  await fetch(`${config.public.apiBase}/sales/${row.id}/void`, { method: 'POST', headers: { Authorization: `Bearer ${auth.token}` } })
  await refresh()
}

async function doDelete(row: any) {
  const ok = await confirm('Delete sale', 'Permanently delete this draft sale?')
  if (!ok) return
  const config = useRuntimeConfig(); const auth = useAuthStore()
  await fetch(`${config.public.apiBase}/sales/${row.id}`, { method: 'DELETE', headers: { Authorization: `Bearer ${auth.token}` } })
  await refresh()
}
</script>
