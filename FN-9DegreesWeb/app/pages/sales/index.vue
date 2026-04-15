<template>
  <NuxtLayout>
    <!-- Filters -->
    <div class="bg-white border border-[#E8E8EC] rounded-2xl p-4 mb-4 shadow-sm">
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
        <AppSelect v-model="listParams.status" :options="statusOpts" placeholder="All Statuses" />
        <AppSelect v-model="listParams.sale_type" :options="typeOpts" placeholder="All Types" />
        <AppSelect v-model="listParams.ambassador_id" :options="ambassadorOpts" placeholder="All Ambassadors" />
        <AppSelect v-model="listParams.month" :options="monthOpts" placeholder="All Months" />
      </div>
      <div class="mt-3 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between border-t border-[#F0F0F0] pt-3">
        <p class="text-[12px] text-gray-500">
          Confirm all applies to every <span class="font-medium text-ink">draft</span> row matching ambassador and month.
        </p>
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center shrink-0">
          <NuxtLink to="/sales/import" class="btn-secondary text-[13px] text-center">
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

    <p class="text-[12px] text-gray-500 mb-2">{{ summaryScopeLabel }}</p>
    <div v-if="salesSummaryCards" class="grid grid-cols-2 gap-3 mb-4">
      <AppCard label="Matching sales" :value="salesSummaryCards.count" />
      <AppCard label="Gross total" prefix="RM " :value="fmtSummaryGross" />
    </div>

    <!-- Table -->
    <AppTable :columns="columns" :rows="sales" :loading="loading">
      <template #default="{ row }">
        <td class="px-4 py-3 text-[13px] text-ink">{{ row.ambassador_name }}</td>
        <td class="px-4 py-3 text-[13px] text-gray-500">{{ formatDate(row.date) }}</td>
        <td class="px-4 py-3 text-[13px] text-gray-500">{{ row.sale_type }}</td>
        <td class="px-4 py-3 text-[13px] text-gray-500">{{ row.table_number ?? '—' }}</td>
        <td class="px-4 py-3 text-[13px] text-right font-semibold text-ink">{{ formatRM(row.gross_amount) }}</td>
        <td class="px-4 py-3"><AppBadge :variant="row.status">{{ row.status }}</AppBadge></td>
        <td class="px-4 py-3">
          <div class="flex justify-end gap-1">
            <button class="act-btn" title="Edit" @click="openEdit(row)">✎</button>
            <button v-if="row.status === 'draft'" class="act-btn text-[#007a80]" title="Confirm" @click="doConfirm(row)">✓</button>
            <button v-if="row.status !== 'void'" class="act-btn text-red-400" title="Void" @click="doVoid(row)">⊘</button>
            <button v-if="row.status === 'draft'" class="act-btn text-red-400" title="Delete" @click="doDelete(row)">✕</button>
          </div>
        </td>
      </template>
    </AppTable>

    <div
      v-if="meta"
      class="bg-white border border-[#E8E8EC] rounded-2xl p-4 mt-4 shadow-sm flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between"
    >
      <p class="text-[13px] text-gray-500 lg:pt-1">
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

    <!-- New Sale FAB (mobile) -->
    <button
      class="fixed bottom-5 right-5 lg:hidden w-12 h-12 bg-[#00C4CC] text-white rounded-full shadow-lg text-2xl flex items-center justify-center"
      @click="openCreate"
    >+</button>

    <!-- New Sale button (desktop) -->
    <div class="fixed bottom-5 right-5 hidden lg:block">
      <button class="btn-primary" @click="openCreate">+ New Sale</button>
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

/** Stable empty query so useAPI does not treat every `{}` as a new request identity for unfiltered summaries. */
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

/** Normalized row from latest-defaults (handles useAPI when `data` is null). */
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
  const ok = await confirm('Confirm Sale', `Confirm this sale for ${row.ambassador_name}?`)
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
  const ok = await confirm('Void Sale', 'This action cannot be undone. Void this sale?')
  if (!ok) return
  const config = useRuntimeConfig(); const auth = useAuthStore()
  await fetch(`${config.public.apiBase}/sales/${row.id}/void`, { method: 'POST', headers: { Authorization: `Bearer ${auth.token}` } })
  await refresh()
}

async function doDelete(row: any) {
  const ok = await confirm('Delete Sale', 'Permanently delete this draft sale?')
  if (!ok) return
  const config = useRuntimeConfig(); const auth = useAuthStore()
  await fetch(`${config.public.apiBase}/sales/${row.id}`, { method: 'DELETE', headers: { Authorization: `Bearer ${auth.token}` } })
  await refresh()
}
</script>
