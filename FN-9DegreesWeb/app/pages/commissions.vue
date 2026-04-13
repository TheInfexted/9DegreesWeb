<template>
  <NuxtLayout>
    <!-- Filters -->
    <div class="bg-white border border-[#E8E8EC] rounded-2xl p-4 mb-4 shadow-sm flex flex-wrap gap-3">
      <AppSelect v-model="listParams.month" :options="monthOpts" placeholder="Select month" class="min-w-[160px]" />
      <AppSelect v-model="listParams.ambassador_id" :options="ambassadorOpts" placeholder="All Ambassadors" class="min-w-[180px]" />
    </div>

    <p class="text-[12px] text-gray-500 mb-2">{{ summaryScopeLabel }}</p>
    <!-- Summary bar -->
    <div v-if="summaryCards" class="grid grid-cols-2 lg:grid-cols-3 gap-3 mb-4">
      <AppCard label="Total Commission" prefix="RM " :value="fmt(summaryCards.total)" />
      <AppCard label="Table Commission" prefix="RM " :value="fmt(summaryCards.table)" />
      <AppCard label="BGO Commission" prefix="RM " :value="fmt(summaryCards.bgo)" />
    </div>

    <div v-if="commissionLegendLines.length" class="space-y-1 mb-2">
      <p v-for="(line, i) in commissionLegendLines" :key="i" class="text-[11px] text-gray-500">
        {{ line }}
      </p>
    </div>

    <!-- Table -->
    <AppTable :columns="columns" :rows="sales" :loading="loading" :get-row-class="commissionRowClass">
      <template #default="{ row }">
        <td class="px-4 py-3 text-[13px]">{{ formatDate(row.date) }}</td>
        <td class="px-4 py-3"><AppBadge :variant="row.sale_type === 'Table' ? 'confirmed' : 'ambassador'">{{ row.sale_type }}</AppBadge></td>
        <td class="px-4 py-3 text-[13px] text-gray-500">{{ row.table_number ?? '—' }}</td>
        <td class="px-4 py-3 text-[13px] text-ink font-medium">{{ row.ambassador_name }}</td>
        <td class="px-4 py-3 text-[13px] text-right font-semibold">{{ formatRM(row.gross_amount) }}</td>
        <td class="px-4 py-3 text-[13px] text-right text-gray-500">{{ displayReportRate(row) }}%</td>
        <td class="px-4 py-3 text-[13px] text-right font-semibold text-[#00A0A6]">{{ formatRM(row.commission_amount) }}</td>
      </template>
    </AppTable>

    <div
      v-if="meta"
      class="bg-white border border-[#E8E8EC] rounded-2xl p-4 mt-4 shadow-sm flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between"
    >
      <p class="text-[13px] text-gray-500 lg:pt-1">
        <template v-if="meta.total === 0">No commission rows for these filters.</template>
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
  </NuxtLayout>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { formatDate } from '~/utils/dateFormat'
import { formatRM } from '~/utils/currency'

definePageMeta({ middleware: 'auth' })

const NO_COMMISSION_SUMMARY_FILTERS: Record<string, unknown> = {}

const listParams = ref({ month: '', ambassador_id: '', page: 1, per_page: 25 })

watch(
  () => [listParams.value.month, listParams.value.ambassador_id].join('\0'),
  () => {
    listParams.value = { ...listParams.value, page: 1 }
  },
)

const commissionsQuery = computed(() => {
  const p = listParams.value
  const o: Record<string, unknown> = { page: p.page, per_page: p.per_page }
  if (p.month) o.month = p.month
  if (p.ambassador_id) o.ambassador_id = p.ambassador_id
  return o
})

const summaryQuery = computed(() => {
  const p = listParams.value
  if (!p.month && !p.ambassador_id) return NO_COMMISSION_SUMMARY_FILTERS
  const o: Record<string, unknown> = {}
  if (p.month) o.month = p.month
  if (p.ambassador_id) o.ambassador_id = p.ambassador_id
  return o
})

const summaryScopeLabel = computed(() => {
  const p = listParams.value
  return p.month || p.ambassador_id
    ? 'Commission totals for the current filters (all matching confirmed sales, not just this page).'
    : 'Commission totals across all confirmed sales (no filters applied).'
})

const { data: sales, loading, meta } = useAPI('commissions', commissionsQuery)
const { data: summaryPayload }       = useAPI('commissions/summary', summaryQuery)
const { data: months }               = useAPI('commissions/months')
const { data: ambassadors }          = useAPI('ambassadors', { status: 'active' })

const monthOpts      = computed(() => (months.value ?? []).map((m: any) => ({ value: m.month, label: m.month })))
const ambassadorOpts = computed(() => (ambassadors.value ?? []).map((a: any) => ({ value: a.id, label: a.name })))

/** Johnny-only commission view: Table rows from other ambassadors are his pool remainder. */
const isJohnnyAmbassadorFilter = computed(() => {
  const id = listParams.value.ambassador_id
  if (id === '' || id == null) return false
  const list = (ambassadors.value ?? []) as { id: number | string; name: string }[]
  const a = list.find((x) => String(x.id) === String(id))
  return a?.name === 'Johnny'
})

function isUnassignedSalesCommissionRow(r: Record<string, unknown>): boolean {
  return String(r.ambassador_name ?? '') === 'Unassigned Sales'
}

function commissionRowClass(row: unknown): string | undefined {
  if (!row || typeof row !== 'object') return undefined
  const r = row as Record<string, unknown>

  if (isUnassignedSalesCommissionRow(r)) {
    return 'bg-[#FFF8E6]/95 border-l-[3px] border-l-[#E6A317] hover:bg-[#FFF3D6]/95'
  }

  if (!isJohnnyAmbassadorFilter.value) return undefined
  if (r.sale_type !== 'Table') return undefined
  const filterId = Number(listParams.value.ambassador_id)
  const aid = Number(r.ambassador_id)
  if (!Number.isFinite(filterId) || !Number.isFinite(aid) || aid === filterId) return undefined
  return 'bg-[#E6F7F8]/90 border-l-[3px] border-l-[#00C4CC] hover:bg-[#D8F0F2]/95'
}

const commissionLegendLines = computed(() => {
  const rows = (sales.value ?? []) as Record<string, unknown>[]
  const lines: string[] = []
  if (rows.some((r) => isUnassignedSalesCommissionRow(r))) {
    lines.push('Amber highlight: sale under Unassigned Sales (Table pool is 12% to owner; BGO stays 10% on that row).')
  }
  if (isJohnnyAmbassadorFilter.value && rows.length > 0) {
    lines.push('Teal highlight: your Table pool share from another ambassador’s sale (12% total minus their rate).')
  }
  return lines
})

const summaryCards = computed(() => {
  const s = summaryPayload.value as { total?: number; table?: number; bgo?: number } | null
  if (s == null || typeof s !== 'object') return null
  return {
    total: Number(s.total ?? 0),
    table: Number(s.table ?? 0),
    bgo:   Number(s.bgo ?? 0),
  }
})

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

const fmt = (n: number) => n.toLocaleString('en-MY', { minimumFractionDigits: 2, maximumFractionDigits: 2 })

function displayReportRate(row: Record<string, unknown>): string {
  const r = row.report_commission_rate
  if (r !== undefined && r !== null && r !== '') return Number(r).toFixed(2)
  return row.confirmed_commission_rate != null ? String(row.confirmed_commission_rate) : '—'
}

const columns = [
  { key: 'date',   label: 'Date'            },
  { key: 'type',   label: 'Type'            },
  { key: 'table',  label: 'Table #'         },
  { key: 'amb',    label: 'Ambassador'      },
  { key: 'gross',  label: 'Gross', align: 'right' as const },
  { key: 'rate',   label: 'Rate',  align: 'right' as const },
  { key: 'comm',   label: 'Commission', align: 'right' as const },
]
</script>
