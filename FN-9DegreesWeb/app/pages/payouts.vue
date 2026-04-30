<template>
  <NuxtLayout>
    <!-- Header actions -->
    <template #header-actions>
      <button class="btn-primary inline-flex items-center gap-1.5" @click="showCreate = true">
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14M5 12h14"/></svg>
        New payout
      </button>
    </template>

    <!-- Filters -->
    <div class="surface p-4 mb-4 flex flex-wrap gap-3">
      <AppSelect v-model="listParams.month" :options="monthOpts" placeholder="All months" class="min-w-[180px]" />
      <AppSelect v-model="filterPaid" :options="paidOpts" placeholder="All statuses" class="min-w-[180px]" />
    </div>

    <!-- Table -->
    <AppTable :columns="columns" :rows="payouts" :loading="loading">
      <template #default="{ row }">
        <td class="px-4 py-3 text-[13px] font-medium text-ink tracking-[-0.005em]">
          <button
            type="button"
            class="text-left rounded-sm hover:text-cyan-dark underline-offset-2 hover:underline transition-colors"
            @click="openDetail(row)"
          >
            {{ row.ambassador_name }}
          </button>
        </td>
        <td class="px-4 py-3 text-[13px] text-text-soft tabular">{{ row.month?.slice(0, 7) }}</td>
        <td class="px-4 py-3 text-[13px] text-right font-semibold text-cyan-dark tabular">{{ formatRM(row.total_commission) }}</td>
        <td class="px-4 py-3"><AppBadge :variant="row.paid_at ? 'paid' : 'unpaid'">{{ row.paid_at ? 'Paid' : 'Unpaid' }}</AppBadge></td>
        <td class="px-4 py-3 text-[13px] text-text-muted tabular">{{ row.paid_at ? formatDate(row.paid_at) : '—' }}</td>
        <td class="px-4 py-3">
          <div class="flex justify-end gap-1 items-center">
            <button v-if="!row.paid_at" class="act-btn text-cyan-dark hover:bg-cyan-tint" title="Mark as paid" @click="doMarkPaid(row)">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
            </button>
            <button class="act-btn" title="Download summary" @click="doDownloadSummary(row)">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3"/></svg>
            </button>
            <button class="act-btn text-[#5B3FA1] hover:bg-[#F3EFFB]" title="Generate payslip" @click="doGeneratePayslip(row)">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6M9 4h6a2 2 0 012 2v14l-3-2-3 2-3-2-3 2V6a2 2 0 012-2z"/></svg>
            </button>
            <button class="act-btn text-cyan-dark hover:bg-cyan-tint relative" title="Receipts" @click="openReceipts(row)">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
              <span v-if="receiptCount(row) > 0" class="absolute -top-1 -right-1 min-w-[14px] h-[14px] px-[3px] rounded-full bg-cyan text-white text-[9px] font-semibold leading-[14px] tabular">{{ receiptCount(row) }}</span>
            </button>
            <button class="act-btn text-[#DC4438] hover:bg-[#FDF2F1]" title="Delete" @click="doDelete(row)">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M6 18L18 6"/></svg>
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

    <PayoutCreateModal v-model="showCreate" @saved="refresh" />
    <PayoutReceiptModal v-model="showReceipts" :payout="receiptRow" @updated="refresh" />

    <AppModal v-model="showDetail" title="Ambassador details" size="sm">
      <div v-if="detailRow" class="space-y-4 text-[13px]">
        <div class="grid grid-cols-[120px_1fr] gap-y-2.5 gap-x-3">
          <span class="text-text-muted">Full name</span>
          <span class="text-ink font-medium tracking-[-0.005em]">{{ detailRow.full_name || detailRow.ambassador_name }}</span>
          <span class="text-text-muted">Role</span>
          <span class="text-ink">{{ detailRow.role_name || '—' }}</span>
          <span class="text-text-muted">Team</span>
          <span class="text-ink">{{ detailRow.team_name || '—' }}</span>
        </div>
        <div class="border-t border-border-soft pt-4 grid grid-cols-[120px_1fr] gap-y-2.5 gap-x-3">
          <span class="text-text-muted">Bank</span>
          <span class="text-ink">{{ detailRow.bank_name || '—' }}</span>
          <span class="text-text-muted">Account no.</span>
          <span
            class="text-ink font-mono tabular text-[12.5px]"
            :class="detailRow.bank_account_number ? 'cursor-pointer select-all hover:text-cyan-dark transition-colors' : ''"
            :title="detailRow.bank_account_number ? 'Click to copy' : ''"
            @click="detailRow.bank_account_number && copyAccountNumber(detailRow.bank_account_number)"
          >{{ detailRow.bank_account_number || '—' }}<span v-if="copiedAccount" class="ml-1 text-cyan-dark text-[11px] font-sans not-italic">copied</span></span>
          <span class="text-text-muted">Holder</span>
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

const monthOpts = computed(() => (months.value ?? []).map((m: any) => ({ value: m.month, label: m.month })))
const paidOpts  = [{ value: '1', label: 'Paid' }, { value: '0', label: 'Unpaid' }]

async function refresh() {
  await refreshPayoutsList()
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
  { key: 'paid_at',    label: 'Paid date'    },
  { key: 'actions',    label: ''             },
]

const { confirm } = useConfirm()

async function doMarkPaid(row: any) {
  const ok = await confirm('Mark as paid', `Mark ${row.ambassador_name}'s payout as paid?`)
  if (!ok) return
  await fetch(`${config.public.apiBase}/payouts/${row.id}/mark-paid`, { method: 'POST', headers: { Authorization: `Bearer ${auth.token}` } })
  await refresh()
}

async function doDownloadSummary(row: any) {
  await downloadPdf(`${config.public.apiBase}/payouts/${row.id}/summary`, 'payout-summary.pdf', auth.token!)
}

async function doGeneratePayslip(row: any) {
  const res = await fetch(`${config.public.apiBase}/payouts/${row.id}/payslip`, { method: 'POST', headers: { Authorization: `Bearer ${auth.token}` } })
  if (res.ok) {
    await downloadPdf(`${config.public.apiBase}/payouts/${row.id}/payslip`, 'payslip.pdf', auth.token!)
    await refresh()
  }
}

async function doDelete(row: any) {
  const ok = await confirm('Delete payout', 'Delete this payout record and all its files?', {
    tone: 'danger', confirmLabel: 'Delete',
  })
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
