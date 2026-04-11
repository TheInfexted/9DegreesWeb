<template>
  <NuxtLayout>
    <!-- Header actions -->
    <template #header-actions>
      <button class="btn-primary" @click="showCreate = true">+ Create Payout</button>
    </template>

    <!-- Filters -->
    <div class="flex flex-wrap gap-3 mb-4">
      <AppSelect v-model="filters.month" :options="monthOpts" placeholder="All Months" class="min-w-[160px]" />
      <AppSelect v-model="filterPaid" :options="paidOpts" placeholder="All Statuses" class="min-w-[160px]" />
    </div>

    <!-- Table -->
    <AppTable :columns="columns" :rows="payouts" :loading="loading">
      <template #default="{ row }">
        <td class="px-4 py-3 text-[13px] font-medium text-ink">{{ row.ambassador_name }}</td>
        <td class="px-4 py-3 text-[13px] text-gray-500">{{ row.month?.slice(0, 7) }}</td>
        <td class="px-4 py-3 text-[13px] text-right font-semibold text-[#00A0A6]">{{ formatRM(row.total_commission) }}</td>
        <td class="px-4 py-3"><AppBadge :variant="row.paid_at ? 'paid' : 'unpaid'">{{ row.paid_at ? 'Paid' : 'Unpaid' }}</AppBadge></td>
        <td class="px-4 py-3 text-[13px] text-gray-400">{{ row.paid_at ? formatDate(row.paid_at) : '—' }}</td>
        <td class="px-4 py-3">
          <div class="flex justify-end gap-1">
            <button v-if="!row.paid_at" class="act-btn text-[#007a80]" title="Mark as Paid" @click="doMarkPaid(row)">✓</button>
            <button class="act-btn" title="Download Summary" @click="doDownloadSummary(row)">↓</button>
            <button class="act-btn text-purple-500" title="Generate Payslip" @click="doGeneratePayslip(row)">📄</button>
            <button class="act-btn text-red-400" title="Delete" @click="doDelete(row)">✕</button>
          </div>
        </td>
      </template>
    </AppTable>

    <PayoutCreateModal v-model="showCreate" @saved="refresh" />
  </NuxtLayout>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { formatDate } from '~/utils/dateFormat'
import { formatRM } from '~/utils/currency'
import { downloadPdf } from '~/utils/download'

definePageMeta({ middleware: 'auth' })

const showCreate  = ref(false)
const filterPaid  = ref('')
const filters     = ref({ month: '' })
const config      = useRuntimeConfig()
const auth        = useAuthStore()

const { data: payouts, loading, refresh } = useAPI('payouts', computed(() => ({
  month: filters.value.month || undefined,
  paid:  filterPaid.value !== '' ? filterPaid.value : undefined,
})))
const { data: months } = useAPI('payouts/months')

const monthOpts = computed(() => (months.value ?? []).map((m: any) => ({ value: m.month, label: m.month })))
const paidOpts  = [{ value: '1', label: 'Paid' }, { value: '0', label: 'Unpaid' }]

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
  refresh()
}

async function doDownloadSummary(row: any) {
  await downloadPdf(`${config.public.apiBase}/payouts/${row.id}/summary`, `payout-summary-${row.id}.pdf`, auth.token!)
}

async function doGeneratePayslip(row: any) {
  const res = await fetch(`${config.public.apiBase}/payouts/${row.id}/payslip`, { method: 'POST', headers: { Authorization: `Bearer ${auth.token}` } })
  if (res.ok) {
    await downloadPdf(`${config.public.apiBase}/payouts/${row.id}/payslip`, `payslip-${row.id}.pdf`, auth.token!)
    refresh()
  }
}

async function doDelete(row: any) {
  const ok = await confirm('Delete Payout', 'Delete this payout record and all its files?')
  if (!ok) return
  await fetch(`${config.public.apiBase}/payouts/${row.id}`, { method: 'DELETE', headers: { Authorization: `Bearer ${auth.token}` } })
  refresh()
}
</script>
