<template>
  <NuxtLayout>
    <!-- Filters -->
    <div class="bg-white border border-[#E8E8EC] rounded-2xl p-4 mb-4 shadow-sm flex flex-wrap gap-3">
      <AppSelect v-model="filters.month" :options="monthOpts" placeholder="Select month" class="min-w-[160px]" />
      <AppSelect v-model="filters.ambassador_id" :options="ambassadorOpts" placeholder="All Ambassadors" class="min-w-[180px]" />
    </div>

    <!-- Summary bar -->
    <div v-if="sales?.length" class="grid grid-cols-2 lg:grid-cols-3 gap-3 mb-4">
      <AppCard label="Total Commission" prefix="RM " :value="fmt(summary.total)" />
      <AppCard label="Table Commission" prefix="RM " :value="fmt(summary.table)" />
      <AppCard label="BGO Commission" prefix="RM " :value="fmt(summary.bgo)" />
    </div>

    <!-- Table -->
    <AppTable :columns="columns" :rows="sales" :loading="loading">
      <template #default="{ row }">
        <td class="px-4 py-3 text-[13px]">{{ formatDate(row.date) }}</td>
        <td class="px-4 py-3"><AppBadge :variant="row.sale_type === 'Table' ? 'confirmed' : 'ambassador'">{{ row.sale_type }}</AppBadge></td>
        <td class="px-4 py-3 text-[13px] text-gray-500">{{ row.table_number ?? '—' }}</td>
        <td class="px-4 py-3 text-[13px] text-ink font-medium">{{ row.ambassador_name }}</td>
        <td class="px-4 py-3 text-[13px] text-right font-semibold">{{ formatRM(row.gross_amount) }}</td>
        <td class="px-4 py-3 text-[13px] text-right text-gray-500">{{ row.confirmed_commission_rate }}%</td>
        <td class="px-4 py-3 text-[13px] text-right font-semibold text-[#00A0A6]">{{ formatRM(row.commission_amount) }}</td>
      </template>
    </AppTable>
  </NuxtLayout>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { formatDate } from '~/utils/dateFormat'
import { formatRM } from '~/utils/currency'

definePageMeta({ middleware: 'auth' })

const filters = ref({ month: '', ambassador_id: '' })

const { data: sales, loading } = useAPI('commissions', filters)
const { data: months }         = useAPI('commissions/months')
const { data: ambassadors }    = useAPI('ambassadors', { status: 'active' })

const monthOpts      = computed(() => (months.value ?? []).map((m: any) => ({ value: m.month, label: m.month })))
const ambassadorOpts = computed(() => (ambassadors.value ?? []).map((a: any) => ({ value: a.id, label: a.name })))

const summary = computed(() => {
  const s = (sales.value ?? []) as any[]
  return {
    total: s.reduce((acc, r) => acc + parseFloat(r.commission_amount), 0),
    table: s.filter(r => r.sale_type === 'Table').reduce((acc, r) => acc + parseFloat(r.commission_amount), 0),
    bgo:   s.filter(r => r.sale_type === 'BGO').reduce((acc, r) => acc + parseFloat(r.commission_amount), 0),
  }
})

const fmt = (n: number) => n.toLocaleString('en-MY', { minimumFractionDigits: 2, maximumFractionDigits: 2 })

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
