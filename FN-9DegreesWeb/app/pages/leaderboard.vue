<template>
  <NuxtLayout>
    <!-- Month chips -->
    <div class="surface p-4 mb-4">
      <div class="flex items-center justify-between mb-3">
        <label class="text-[10.5px] font-semibold uppercase tracking-[0.1em] text-text-muted">Months</label>
        <div class="flex items-center gap-3">
          <button class="text-[11.5px] text-cyan-dark font-medium hover:underline underline-offset-2" @click="selectAll">All</button>
          <span class="w-px h-3 bg-border" aria-hidden="true" />
          <button class="text-[11.5px] text-text-muted hover:text-ink font-medium" @click="selectedMonths = []">Clear</button>
        </div>
      </div>
      <div class="flex flex-wrap gap-2">
        <button
          v-for="m in availableMonths"
          :key="m.month"
          type="button"
          class="px-3 py-1.5 rounded-md text-[11.5px] font-medium border transition-all duration-150 active:scale-[0.97] tabular"
          :class="selectedMonths.includes(m.month)
            ? 'bg-cyan-tint border-cyan/40 text-cyan-dark shadow-[inset_0_0_0_1px_rgba(0,181,189,0.10)]'
            : 'border-border bg-white text-text-soft hover:border-[#D8D7D2] hover:text-ink'"
          @click="toggleMonth(m.month)"
        >{{ m.month }}</button>
      </div>
    </div>

    <!-- Rankings table -->
    <AppTable :columns="columns" :rows="rankings" :loading="loading">
      <template #default="{ row }">
        <td class="px-4 py-3 text-[13px] tabular">
          <span
            class="inline-flex items-center justify-center w-6 h-6 rounded-md font-semibold text-[12px]"
            :class="row.$rank === 1
              ? 'bg-cyan text-white'
              : row.$rank <= 3
                ? 'bg-cyan-tint text-cyan-dark'
                : 'bg-border-soft text-text-soft'"
          >{{ row.$rank }}</span>
        </td>
        <td class="px-4 py-3 text-[13px] font-medium text-ink tracking-[-0.005em]">{{ row.ambassador_name }}</td>
        <td class="px-4 py-3 text-[13px] text-text-soft">{{ row.role_name }}</td>
        <td class="px-4 py-3 text-[13px] text-text-soft">{{ row.team_name ?? '—' }}</td>
        <td class="px-4 py-3 text-[13px] text-right text-text-soft tabular">{{ row.sale_count }}</td>
        <td class="px-4 py-3 text-[13px] text-right font-semibold text-ink tabular">{{ formatRM(row.total_amount) }}</td>
      </template>
    </AppTable>
  </NuxtLayout>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { formatRM } from '~/utils/currency'

definePageMeta({ middleware: 'auth' })

const selectedMonths = ref<string[]>([])
const { data: monthsData }   = useAPI('leaderboard/months')
const availableMonths        = computed(() => (monthsData.value ?? []) as any[])
const { data: raw, loading } = useAPI('leaderboard', computed(() => ({ months: selectedMonths.value })))

const rankings = computed(() =>
  ((raw.value ?? []) as any[]).map((r, i) => ({ ...r, $rank: i + 1 }))
)

const selectAll = () => { selectedMonths.value = availableMonths.value.map((m: any) => m.month) }
const toggleMonth = (m: string) => {
  const i = selectedMonths.value.indexOf(m)
  i === -1 ? selectedMonths.value.push(m) : selectedMonths.value.splice(i, 1)
}

const columns = [
  { key: 'rank',  label: '#'           },
  { key: 'name',  label: 'Ambassador'  },
  { key: 'role',  label: 'Role'        },
  { key: 'team',  label: 'Team'        },
  { key: 'count', label: 'Sales',  align: 'right' as const },
  { key: 'total', label: 'Total (RM)', align: 'right' as const },
]
</script>
