<template>
  <NuxtLayout>
    <!-- Header actions -->
    <template #header-actions>
      <button class="btn-primary" @click="showCreate = true">+ Create Payout</button>
    </template>

    <!-- Filters -->
    <div class="flex flex-wrap gap-3 mb-4">
      <AppSelect v-model="listParams.month" :options="monthOpts" placeholder="All Months" class="min-w-[160px]" />
      <AppSelect v-model="filterPaid" :options="paidOpts" placeholder="All Statuses" class="min-w-[160px]" />
    </div>

    <!--
      Payouts summary (optional) — uncomment this block and the script block marked below to restore
      GET /payouts/summary-driven count + commission total cards.

    <p class="text-[12px] text-gray-500 mb-2">{{ summaryScopeLabel }}</p>
    <div v-if="payoutSummaryCards" class="grid grid-cols-2 gap-3 mb-4">
      <AppCard label="Matching payouts" :value="payoutSummaryCards.count" />
      <AppCard label="Commission total" prefix="RM " :value="fmtPayoutCommission" />
    </div>
    -->

    <!-- Table -->
    <AppTable :columns="columns" :rows="payouts" :loading="loading">
      <template #default="{ row }">
        <td class="px-4 py-3 text-[13px] font-medium text-ink">
          <button type="button" class="hover:underline hover:text-[#00A0A6] text-left" @click="openDetail(row)">
            {{ row.ambassador_name }}
          </button>
        </td>
        <td class="px-4 py-3 text-[13px] text-gray-500">{{ row.month?.slice(0, 7) }}</td>
        <td class="px-4 py-3 text-[13px] text-right font-semibold text-[#00A0A6]">{{ formatRM(row.total_commission) }}</td>
        <td class="px-4 py-3"><AppBadge :variant="row.paid_at ? 'paid' : 'unpaid'">{{ row.paid_at ? 'Paid' : 'Unpaid' }}</AppBadge></td>
        <td class="px-4 py-3 text-[13px] text-gray-400">{{ row.paid_at ? formatDate(row.paid_at) : '—' }}</td>
        <td class="px-4 py-3">
          <div class="flex justify-end gap-1 items-center">
            <button v-if="!row.paid_at" class="act-btn text-[#007a80]" title="Mark as Paid" @click="doMarkPaid(row)">✓</button>
            <button class="act-btn" title="Download Summary" @click="doDownloadSummary(row)">↓</button>
            <button class="act-btn text-purple-500" title="Generate Payslip" @click="doGeneratePayslip(row)">📄</button>
            <button class="act-btn text-[#00A0A6]" title="Receipts" @click="openReceipts(row)">
              📎<span v-if="receiptCount(row) > 0" class="ml-0.5 text-[10px] font-bold">{{ receiptCount(row) }}</span>
            </button>
            <button class="act-btn text-red-400" title="Delete" @click="doDelete(row)">✕</button>
          </div>
        </td>
      </template>
    </AppTable>

    <div
      v-if="meta"
      class="bg-white border border-[#E8E8EC] rounded-2xl p-4 mt-4 shadow-sm flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between"
    >
      <p class="text-[13px] text-gray-500 lg:pt-1">
        <template v-if="meta.total === 0">No payouts match these filters.</template>
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

    <PayoutCreateModal v-model="showCreate" @saved="refresh" />
    <PayoutReceiptModal v-model="showReceipts" :payout="receiptRow" @updated="refresh" />

    <AppModal v-model="showDetail" title="Ambassador Details" size="sm">
      <div v-if="detailRow" class="space-y-3 text-[13px]">
        <div class="grid grid-cols-[110px_1fr] gap-y-2">
          <span class="text-gray-400">Full Name</span>
          <span class="text-ink font-medium">{{ detailRow.full_name || detailRow.ambassador_name }}</span>
          <span class="text-gray-400">Role</span>
          <span class="text-ink">{{ detailRow.role_name || '—' }}</span>
          <span class="text-gray-400">Team</span>
          <span class="text-ink">{{ detailRow.team_name || '—' }}</span>
        </div>
        <hr class="border-[#F0F0F0]" />
        <div class="grid grid-cols-[110px_1fr] gap-y-2">
          <span class="text-gray-400">Bank</span>
          <span class="text-ink">{{ detailRow.bank_name || '—' }}</span>
          <span class="text-gray-400">Account No.</span>
          <span
            class="text-ink font-mono"
            :class="detailRow.bank_account_number ? 'cursor-pointer select-all hover:text-blue-600 transition-colors' : ''"
            :title="detailRow.bank_account_number ? 'Click to copy' : ''"
            @click="detailRow.bank_account_number && copyAccountNumber(detailRow.bank_account_number)"
          >{{ detailRow.bank_account_number || '—' }}{{ copiedAccount ? ' ✓' : '' }}</span>
          <span class="text-gray-400">Account Owner</span>
          <span class="text-ink">{{ detailRow.bank_owner_name || '—' }}</span>
        </div>
      </div>
    </AppModal>
  </NuxtLayout>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { formatDate } from '~/utils/dateFormat'
import { formatRM } from '~/utils/currency'
import { downloadPdf } from '~/utils/download'

definePageMeta({ middleware: 'auth' })

const showCreate    = ref(false)
const showReceipts  = ref(false)
const receiptRow    = ref<any>(null)
const showDetail    = ref(false)
const detailRow     = ref<any>(null)
const copiedAccount = ref(false)

function copyAccountNumber(value: string) {
  navigator.clipboard.writeText(value)
  copiedAccount.value = true
  setTimeout(() => { copiedAccount.value = false }, 2000)
}
const filterPaid  = ref('')
const listParams  = ref({ month: '', page: 1, per_page: 25 })
const config      = useRuntimeConfig()
const auth        = useAuthStore()

watch([() => listParams.value.month, filterPaid], () => {
  listParams.value = { ...listParams.value, page: 1 }
})

const payoutsQuery = computed(() => {
  const p = listParams.value
  const o: Record<string, unknown> = { page: p.page, per_page: p.per_page }
  if (p.month) o.month = p.month
  if (filterPaid.value !== '') o.paid = filterPaid.value
  return o
})

const { data: payouts, loading, meta, refresh: refreshPayoutsList } = useAPI('payouts', payoutsQuery)
const { data: months } = useAPI('payouts/months')

/*
 * --- Payouts summary (optional) — uncomment template + this block; then change refresh() below ---
 *
 * const NO_PAYOUT_SUMMARY_FILTERS: Record<string, unknown> = {}
 *
 * const payoutsSummaryParams = computed(() => {
 *   const p = listParams.value
 *   if (!p.month && filterPaid.value === '') return NO_PAYOUT_SUMMARY_FILTERS
 *   const o: Record<string, unknown> = {}
 *   if (p.month) o.month = p.month
 *   if (filterPaid.value !== '') o.paid = filterPaid.value
 *   return o
 * })
 *
 * const { data: payoutSummaryRaw, refresh: refreshPayoutsSummary } = useAPI('payouts/summary', payoutsSummaryParams)
 *
 * const payoutSummaryCards = computed(() => {
 *   const s = payoutSummaryRaw.value as { count?: number; commission_total?: number } | null
 *   if (s == null || typeof s !== 'object') return null
 *   return {
 *     count:              Number(s.count ?? 0),
 *     commission_total:   Number(s.commission_total ?? 0),
 *   }
 * })
 *
 * const summaryScopeLabel = computed(() => {
 *   const filtered = !!(listParams.value.month || filterPaid.value !== '')
 *   return filtered
 *     ? 'Summary totals for the current filters (all matching rows, not just this page).'
 *     : 'Summary totals across all payouts (no filters applied).'
 * })
 *
 * const fmtPayoutCommission = computed(() =>
 *   (payoutSummaryCards.value?.commission_total ?? 0).toLocaleString('en-MY', {
 *     minimumFractionDigits: 2,
 *     maximumFractionDigits: 2,
 *   }),
 * )
 */

const monthOpts = computed(() => (months.value ?? []).map((m: any) => ({ value: m.month, label: m.month })))
const paidOpts  = [{ value: '1', label: 'Paid' }, { value: '0', label: 'Unpaid' }]

async function refresh() {
  await refreshPayoutsList()
  // With summary restored: await Promise.all([refreshPayoutsList(), refreshPayoutsSummary()])
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
  { key: 'ambassador', label: 'Ambassador'   },
  { key: 'month',      label: 'Month'        },
  { key: 'total',      label: 'Commission', align: 'right' as const },
  { key: 'status',     label: 'Status'       },
  { key: 'paid_at',    label: 'Paid Date'    },
  { key: 'actions',    label: ''             },
]

const { confirm } = useConfirm()

async function doMarkPaid(row: any) {
  const ok = await confirm('Mark as Paid', `Mark ${row.ambassador_name}'s payout as paid?`)
  if (!ok) return
  await fetch(`${config.public.apiBase}/payouts/${row.id}/mark-paid`, { method: 'POST', headers: { Authorization: `Bearer ${auth.token}` } })
  await refresh()
}

async function doDownloadSummary(row: any) {
  await downloadPdf(`${config.public.apiBase}/payouts/${row.id}/summary`, `payout-summary-${row.id}.pdf`, auth.token!)
}

async function doGeneratePayslip(row: any) {
  const res = await fetch(`${config.public.apiBase}/payouts/${row.id}/payslip`, { method: 'POST', headers: { Authorization: `Bearer ${auth.token}` } })
  if (res.ok) {
    await downloadPdf(`${config.public.apiBase}/payouts/${row.id}/payslip`, `payslip-${row.id}.pdf`, auth.token!)
    await refresh()
  }
}

async function doDelete(row: any) {
  const ok = await confirm('Delete Payout', 'Delete this payout record and all its files?')
  if (!ok) return
  await fetch(`${config.public.apiBase}/payouts/${row.id}`, { method: 'DELETE', headers: { Authorization: `Bearer ${auth.token}` } })
  await refresh()
}

function openReceipts(row: any) {
  receiptRow.value   = row
  showReceipts.value = true
}

function receiptCount(row: any): number {
  if (!row?.receipt_paths) return 0
  try {
    const parsed = JSON.parse(row.receipt_paths)
    return Array.isArray(parsed) ? parsed.length : 0
  } catch {
    return 0
  }
}

function openDetail(row: any) {
  detailRow.value  = row
  copiedAccount.value = false
  showDetail.value = true
}
</script>
