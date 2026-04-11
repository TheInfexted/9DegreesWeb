<template>
  <NuxtLayout>
    <!-- Filters -->
    <div class="bg-white border border-[#E8E8EC] rounded-2xl p-4 mb-4 shadow-sm">
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
        <AppSelect v-model="filters.status" :options="statusOpts" placeholder="All Statuses" />
        <AppSelect v-model="filters.sale_type" :options="typeOpts" placeholder="All Types" />
        <AppSelect v-model="filters.ambassador_id" :options="ambassadorOpts" placeholder="All Ambassadors" />
        <AppSelect v-model="filters.month" :options="monthOpts" placeholder="All Months" />
      </div>
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
      @saved="refresh"
    />
  </NuxtLayout>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { formatDate } from '~/utils/dateFormat'
import { formatRM } from '~/utils/currency'

definePageMeta({ middleware: 'auth' })

const filters = ref({ status: '', sale_type: '', ambassador_id: '', month: '' })
const showForm = ref(false)
const editSale = ref<any>(null)

const { data: sales, loading, refresh } = useAPI('sales', filters)
const { data: ambassadors }             = useAPI('ambassadors', { status: 'active' })
const { data: months }                  = useAPI('sales/months')

const statusOpts     = [{ value: 'draft', label: 'Draft' }, { value: 'confirmed', label: 'Confirmed' }, { value: 'void', label: 'Void' }]
const typeOpts       = [{ value: 'Table', label: 'Table' }, { value: 'BGO', label: 'BGO' }]
const ambassadorOpts = computed(() => (ambassadors.value ?? []).map((a: any) => ({ value: a.id, label: a.name })))
const monthOpts      = computed(() => (months.value ?? []).map((m: any) => ({ value: m.month, label: m.month })))

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

function openCreate() { editSale.value = null; showForm.value = true }
function openEdit(row: any) { editSale.value = row; showForm.value = true }

async function doConfirm(row: any) {
  const ok = await confirm('Confirm Sale', `Confirm this sale for ${row.ambassador_name}? The commission rate will be frozen.`)
  if (!ok) return
  const config = useRuntimeConfig(); const auth = useAuthStore()
  await fetch(`${config.public.apiBase}/sales/${row.id}/confirm`, { method: 'POST', headers: { Authorization: `Bearer ${auth.token}` } })
  refresh()
}

async function doVoid(row: any) {
  const ok = await confirm('Void Sale', 'This action cannot be undone. Void this sale?')
  if (!ok) return
  const config = useRuntimeConfig(); const auth = useAuthStore()
  await fetch(`${config.public.apiBase}/sales/${row.id}/void`, { method: 'POST', headers: { Authorization: `Bearer ${auth.token}` } })
  refresh()
}

async function doDelete(row: any) {
  const ok = await confirm('Delete Sale', 'Permanently delete this draft sale?')
  if (!ok) return
  const config = useRuntimeConfig(); const auth = useAuthStore()
  await fetch(`${config.public.apiBase}/sales/${row.id}`, { method: 'DELETE', headers: { Authorization: `Bearer ${auth.token}` } })
  refresh()
}
</script>
