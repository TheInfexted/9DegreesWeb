<template>
  <NuxtLayout>
    <!-- Month toggle -->
    <div class="bg-white border border-[#E8E8EC] rounded-2xl p-4 mb-4 shadow-sm">
      <div class="flex items-center justify-between mb-3">
        <label class="text-[11px] font-bold uppercase tracking-wide text-gray-400">Select Months</label>
        <div class="flex gap-2">
          <button class="text-[11px] text-[#00A0A6]" @click="selectAll">All</button>
          <span class="text-gray-300">·</span>
          <button class="text-[11px] text-gray-400" @click="selectedMonths = []">Clear</button>
        </div>
      </div>
      <div class="flex flex-wrap gap-2">
        <button
          v-for="m in availableMonths"
          :key="m.month"
          class="px-3 py-1.5 rounded-full text-[11px] font-semibold border transition-colors"
          :class="selectedMonths.includes(m.month)
            ? 'bg-[#00C4CC12] border-[#00C4CC] text-[#007a80]'
            : 'border-[#E8E8EC] text-gray-400 hover:border-gray-300'"
          @click="toggleMonth(m.month)"
        >{{ m.month }}</button>
      </div>
    </div>

    <!-- Rankings table -->
    <AppTable :columns="columns" :rows="rankings" :loading="loading">
      <template #default="{ row }">
        <td class="px-4 py-3 text-[13px] font-bold text-[#00A0A6]">#{{ row.$rank }}</td>
        <td class="px-4 py-3 text-[13px] font-medium text-ink">{{ row.ambassador_name }}</td>
        <td class="px-4 py-3 text-[13px] text-gray-500">{{ row.role_name }}</td>
        <td class="px-4 py-3 text-[13px] text-gray-500">{{ row.team_name ?? '—' }}</td>
        <td class="px-4 py-3 text-[13px] text-right text-gray-500">{{ row.sale_count }}</td>
        <td class="px-4 py-3 text-[13px] text-right font-bold text-ink">{{ formatRM(row.total_amount) }}</td>
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
