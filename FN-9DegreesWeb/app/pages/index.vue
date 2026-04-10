<template>
  <NuxtLayout>
    <div class="space-y-5">
      <!-- Month filter -->
      <div class="flex items-center justify-between">
        <p class="text-[13px] text-gray-400">Overview for</p>
        <select
          v-model="selectedMonth"
          class="px-3 py-1.5 border border-[#E0E0E0] rounded-lg text-[12px] bg-white outline-none focus:border-[#00C4CC]"
        >
          <option value="">All Time</option>
          <option v-for="m in months?.map((r: any) => r.month)" :key="m" :value="m">
            {{ formatMonthLabel(m) }}
          </option>
        </select>
      </div>

      <!-- KPI Cards -->
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
        <AppCard label="Total Sales" prefix="RM " :value="formatNumber(stats.totalSales)" :trend="stats.salesTrend" :trend-dir="stats.salesTrendDir" />
        <AppCard label="Commission" prefix="RM " :value="formatNumber(stats.totalCommission)" :trend="stats.commTrend" :trend-dir="stats.commTrendDir" />
        <AppCard label="Ambassadors" :value="stats.activeAmbassadors" />
        <AppCard label="Active Teams" :value="stats.activeTeams" />
      </div>

      <!-- Charts -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <div class="bg-white border border-[#E8E8EC] rounded-2xl p-5 shadow-sm">
          <h3 class="text-[12px] font-bold uppercase tracking-wide text-gray-400 mb-4">Sales — Last 6 Months</h3>
          <canvas ref="salesChart" height="120" />
        </div>
        <div class="bg-white border border-[#E8E8EC] rounded-2xl p-5 shadow-sm">
          <h3 class="text-[12px] font-bold uppercase tracking-wide text-gray-400 mb-4">Commission — Last 6 Months</h3>
          <canvas ref="commChart" height="120" />
        </div>
      </div>

      <!-- Ambassador Performance Table -->
      <div class="bg-white border border-[#E8E8EC] rounded-2xl overflow-hidden shadow-sm">
        <div class="px-5 py-3 border-b border-[#F0F0F0]">
          <h3 class="text-[12px] font-bold uppercase tracking-wide text-gray-400">Ambassador Performance</h3>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full">
            <thead>
              <tr class="bg-[#FAFAFA] border-b border-[#F0F0F0]">
                <th class="px-4 py-2.5 text-left text-[10px] font-bold uppercase tracking-wide text-gray-300">Ambassador</th>
                <th class="px-4 py-2.5 text-left text-[10px] font-bold uppercase tracking-wide text-gray-300">Team</th>
                <th class="px-4 py-2.5 text-right text-[10px] font-bold uppercase tracking-wide text-gray-300">Sales (RM)</th>
                <th class="px-4 py-2.5 text-right text-[10px] font-bold uppercase tracking-wide text-gray-300">Commission</th>
              </tr>
            </thead>
            <tbody>
              <tr v-if="commLoading">
                <td colspan="4" class="px-4 py-8 text-center text-[13px] text-gray-400">Loading…</td>
              </tr>
              <tr
                v-for="row in ambassadorRows"
                :key="row.ambassador_name"
                class="border-b border-[#F8F8F8] hover:bg-[#FAFCFC] last:border-b-0"
              >
                <td class="px-4 py-3 text-[13px] font-medium text-ink">{{ row.ambassador_name }}</td>
                <td class="px-4 py-3 text-[13px] text-gray-500">{{ row.team_name ?? '—' }}</td>
                <td class="px-4 py-3 text-[13px] text-right font-semibold text-ink">{{ formatRM(row.total_gross) }}</td>
                <td class="px-4 py-3 text-[13px] text-right font-semibold text-[#00A0A6]">{{ formatRM(row.total_commission) }}</td>
              </tr>
              <tr v-if="!commLoading && !ambassadorRows.length">
                <td colspan="4" class="px-4 py-8 text-center text-[13px] text-gray-400">No confirmed sales for this period.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </NuxtLayout>
</template>

<script setup lang="ts">
import { ref, computed, watch, onMounted, nextTick } from 'vue'
import Chart from 'chart.js/auto'
import { formatMonthLabel } from '~/utils/dateFormat'
import { formatRM } from '~/utils/currency'

definePageMeta({ middleware: 'auth' })

const selectedMonth = ref('')
const salesChart    = ref<HTMLCanvasElement | null>(null)
const commChart     = ref<HTMLCanvasElement | null>(null)
let salesChartInst: Chart | null = null
let commChartInst: Chart | null  = null

const { data: months }       = useAPI('commissions/months')
const { data: commData, loading: commLoading } = useAPI('commissions', computed(() => ({
  month: selectedMonth.value || undefined,
})))

const { data: ambassadors }  = useAPI('ambassadors', { status: 'active' })
const { data: teams }        = useAPI('teams')

const stats = computed(() => {
  const sales = (commData.value ?? []) as any[]
  const totalSales       = sales.reduce((s: number, r: any) => s + parseFloat(r.gross_amount), 0)
  const totalCommission  = sales.reduce((s: number, r: any) => s + parseFloat(r.commission_amount), 0)
  return {
    totalSales,
    totalCommission,
    activeAmbassadors: (ambassadors.value ?? []).length,
    activeTeams: (teams.value ?? []).filter((t: any) => t.status === 'active').length,
    salesTrend: '',
    salesTrendDir: 'neutral' as const,
    commTrend: '',
    commTrendDir: 'neutral' as const,
  }
})

const ambassadorRows = computed(() => {
  const sales = (commData.value ?? []) as any[]
  const map   = new Map<string, any>()
  for (const s of sales) {
    const key = s.ambassador_name
    if (!map.has(key)) map.set(key, { ambassador_name: key, team_name: s.team_name, total_gross: 0, total_commission: 0 })
    const row = map.get(key)
    row.total_gross      += parseFloat(s.gross_amount)
    row.total_commission += parseFloat(s.commission_amount)
  }
  return Array.from(map.values()).sort((a, b) => b.total_gross - a.total_gross)
})

function formatNumber(n: number): string {
  return n.toLocaleString('en-MY', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

function renderCharts() {
  if (!salesChart.value || !commChart.value) return
  const CYAN   = '#00C4CC'
  const labels = ['5mo ago','4mo ago','3mo ago','2mo ago','Last mo','This mo']

  if (salesChartInst) salesChartInst.destroy()
  salesChartInst = new Chart(salesChart.value, {
    type: 'line',
    data: { labels, datasets: [{ data: [0,0,0,0,0,stats.value.totalSales], borderColor: CYAN, backgroundColor: CYAN + '15', fill: true, tension: 0.4, pointBackgroundColor: CYAN }] },
    options: { plugins: { legend: { display: false } }, scales: { x: { grid: { display: false } }, y: { grid: { color: '#F0F0F0' } } } },
  })

  if (commChartInst) commChartInst.destroy()
  commChartInst = new Chart(commChart.value, {
    type: 'line',
    data: { labels, datasets: [{ data: [0,0,0,0,0,stats.value.totalCommission], borderColor: CYAN, backgroundColor: CYAN + '15', fill: true, tension: 0.4, pointBackgroundColor: CYAN }] },
    options: { plugins: { legend: { display: false } }, scales: { x: { grid: { display: false } }, y: { grid: { color: '#F0F0F0' } } } },
  })
}

watch(stats, () => nextTick(renderCharts))
onMounted(() => nextTick(renderCharts))
</script>
