<template>
  <NuxtLayout>
    <div class="space-y-6">
      <!-- Section header + month filter -->
      <div class="flex items-end justify-between gap-4 flex-wrap">
        <div>
          <p class="text-[10.5px] font-semibold text-text-muted uppercase tracking-[0.1em]">Overview</p>
          <h2 class="text-[20px] font-semibold text-ink tracking-tightest mt-0.5">
            {{ selectedMonth ? formatMonthLabel(selectedMonth) : 'All time' }}
          </h2>
        </div>
        <div class="flex items-center gap-2">
          <span class="text-[12px] text-text-muted hidden sm:inline">Showing</span>
          <select
            v-model="selectedMonth"
            class="field-input py-1.5 pr-8 cursor-pointer"
          >
            <option value="">All time</option>
            <option v-for="m in months?.map((r: any) => r.month)" :key="m" :value="m">
              {{ formatMonthLabel(m) }}
            </option>
          </select>
        </div>
      </div>

      <!-- KPI Cards -->
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <AppCard label="Total Sales"   prefix="RM " :value="formatNumber(stats.totalSales)"      :trend="stats.salesTrend" :trend-dir="stats.salesTrendDir" />
        <AppCard label="Commission"    prefix="RM " :value="formatNumber(stats.totalCommission)" :trend="stats.commTrend"  :trend-dir="stats.commTrendDir" />
        <AppCard label="Ambassadors"   :value="stats.activeAmbassadors" :accent="false" />
        <AppCard label="Active Teams"  :value="stats.activeTeams"       :accent="false" />
      </div>

      <!-- Charts -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <div class="surface p-5 lg:p-6">
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-[10.5px] font-semibold text-text-muted uppercase tracking-[0.1em]">Sales — last 6 months</h3>
            <span class="w-2 h-2 rounded-full bg-cyan ring-4 ring-cyan-tint" aria-hidden="true" />
          </div>
          <canvas ref="salesChart" height="120" />
        </div>
        <div class="surface p-5 lg:p-6">
          <div class="flex items-center justify-between mb-4">
            <h3 class="text-[10.5px] font-semibold text-text-muted uppercase tracking-[0.1em]">Commission — last 6 months</h3>
            <span class="w-2 h-2 rounded-full bg-cyan ring-4 ring-cyan-tint" aria-hidden="true" />
          </div>
          <canvas ref="commChart" height="120" />
        </div>
      </div>

      <!-- Ambassador Performance Table -->
      <div class="surface overflow-hidden">
        <div class="px-5 py-3.5 border-b border-border-soft flex items-center justify-between">
          <h3 class="text-[10.5px] font-semibold text-text-muted uppercase tracking-[0.1em]">Ambassador performance</h3>
          <span class="text-[11px] text-text-muted tabular">{{ ambassadorRows.length }} {{ ambassadorRows.length === 1 ? 'person' : 'people' }}</span>
        </div>
        <div class="overflow-x-auto">
          <table class="w-full">
            <thead>
              <tr class="bg-border-soft/50 border-b border-border">
                <th class="px-4 py-3 text-left text-[10.5px] font-semibold uppercase tracking-[0.08em] text-text-muted">Ambassador</th>
                <th class="px-4 py-3 text-left text-[10.5px] font-semibold uppercase tracking-[0.08em] text-text-muted">Team</th>
                <th class="px-4 py-3 text-right text-[10.5px] font-semibold uppercase tracking-[0.08em] text-text-muted tabular">Sales (RM)</th>
                <th class="px-4 py-3 text-right text-[10.5px] font-semibold uppercase tracking-[0.08em] text-text-muted tabular">Commission</th>
              </tr>
            </thead>
            <tbody>
              <template v-if="commLoading">
                <tr v-for="n in 4" :key="`sk-${n}`" class="border-b border-border-soft last:border-b-0">
                  <td class="px-4 py-3.5"><div class="skeleton h-3.5" style="width:65%" /></td>
                  <td class="px-4 py-3.5"><div class="skeleton h-3.5" style="width:45%" /></td>
                  <td class="px-4 py-3.5"><div class="skeleton h-3.5 ml-auto" style="width:50%" /></td>
                  <td class="px-4 py-3.5"><div class="skeleton h-3.5 ml-auto" style="width:55%" /></td>
                </tr>
              </template>
              <tr
                v-for="row in ambassadorRows"
                v-else
                :key="row.ambassador_name"
                class="border-b border-border-soft hover:bg-border-soft/40 transition-colors duration-100 last:border-b-0"
              >
                <td class="px-4 py-3 text-[13px] font-medium text-ink tracking-[-0.005em]">{{ row.ambassador_name }}</td>
                <td class="px-4 py-3 text-[13px] text-text-soft">{{ row.team_name ?? '—' }}</td>
                <td class="px-4 py-3 text-[13px] text-right font-medium text-ink tabular">{{ formatRM(row.total_gross) }}</td>
                <td class="px-4 py-3 text-[13px] text-right font-semibold text-cyan-dark tabular">{{ formatRM(row.total_commission) }}</td>
              </tr>
              <tr v-if="!commLoading && !ambassadorRows.length">
                <td colspan="4" class="px-4 py-12 text-center">
                  <div class="flex flex-col items-center gap-2">
                    <div class="w-10 h-10 rounded-full bg-border-soft flex items-center justify-center text-text-muted">
                      <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                      </svg>
                    </div>
                    <p class="text-[13px] text-text-soft">No confirmed sales for this period</p>
                  </div>
                </td>
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
const { data: chartData }    = useAPI<Array<{ month: string; total_sales: number; total_commission: number }>>('commissions/chart')

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
  const CYAN     = '#00B5BD'
  const CYAN_DK  = '#00969C'
  const rows   = (chartData.value ?? []) as Array<{ month: string; total_sales: number; total_commission: number }>
  const labels = rows.map((r) => formatMonthLabel(r.month))
  const salesSeries = rows.map((r) => Number(r.total_sales) || 0)
  const commSeries  = rows.map((r) => Number(r.total_commission) || 0)

  const baseOptions = {
    plugins: { legend: { display: false }, tooltip: { backgroundColor: '#0E0F10', padding: 10, cornerRadius: 8, titleFont: { size: 11 }, bodyFont: { size: 12 } } },
    scales: {
      x: { grid: { display: false }, ticks: { color: '#8A8F99', font: { size: 11 } } },
      y: { grid: { color: '#EFEEE9' }, ticks: { color: '#8A8F99', font: { size: 11 } } },
    },
    elements: { point: { radius: 3, hoverRadius: 5, borderWidth: 2, borderColor: '#FFFFFF' } },
  } as const

  if (salesChartInst) salesChartInst.destroy()
  salesChartInst = new Chart(salesChart.value, {
    type: 'line',
    data: {
      labels,
      datasets: [{
        data: salesSeries,
        borderColor: CYAN,
        backgroundColor: (ctx: any) => {
          const { chart } = ctx
          const { ctx: c, chartArea } = chart
          if (!chartArea) return CYAN + '15'
          const g = c.createLinearGradient(0, chartArea.top, 0, chartArea.bottom)
          g.addColorStop(0, 'rgba(0,181,189,0.22)')
          g.addColorStop(1, 'rgba(0,181,189,0)')
          return g
        },
        fill: true, tension: 0.4, pointBackgroundColor: CYAN_DK, borderWidth: 2,
      }],
    },
    options: baseOptions,
  })

  if (commChartInst) commChartInst.destroy()
  commChartInst = new Chart(commChart.value, {
    type: 'line',
    data: {
      labels,
      datasets: [{
        data: commSeries,
        borderColor: CYAN,
        backgroundColor: (ctx: any) => {
          const { chart } = ctx
          const { ctx: c, chartArea } = chart
          if (!chartArea) return CYAN + '15'
          const g = c.createLinearGradient(0, chartArea.top, 0, chartArea.bottom)
          g.addColorStop(0, 'rgba(0,181,189,0.22)')
          g.addColorStop(1, 'rgba(0,181,189,0)')
          return g
        },
        fill: true, tension: 0.4, pointBackgroundColor: CYAN_DK, borderWidth: 2,
      }],
    },
    options: baseOptions,
  })
}

watch(chartData, () => nextTick(renderCharts))
onMounted(() => nextTick(renderCharts))
</script>
