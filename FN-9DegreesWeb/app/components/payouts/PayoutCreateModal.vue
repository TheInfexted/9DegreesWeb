<template>
  <AppModal :model-value="modelValue" title="Create payout" size="md" @update:model-value="$emit('update:modelValue', $event)">
    <div class="space-y-4">
      <div>
        <label class="field-label">Month</label>
        <AppSelect v-model="selectedMonth" :options="monthOpts" placeholder="Select month" />
      </div>
      <div v-if="selectedMonth">
        <div class="flex items-center justify-between mb-1">
          <label class="field-label !mb-0">Ambassadors</label>
          <span class="text-[11px] text-text-muted tabular">{{ selected.size }} of {{ eligibleAmbassadors.length }} selected</span>
        </div>
        <p class="text-[11.5px] text-text-muted mb-2">Only ambassadors with confirmed sales in this month. Johnny and Unassigned Sales are excluded.</p>
        <div class="border border-border rounded-lg overflow-hidden max-h-64 overflow-y-auto bg-white">
          <div v-if="loadingMonth" class="px-4 py-6 space-y-2">
            <div v-for="n in 3" :key="n" class="flex items-center gap-3">
              <div class="skeleton w-4 h-4 rounded" />
              <div class="flex-1 space-y-1.5">
                <div class="skeleton h-3" style="width:55%" />
                <div class="skeleton h-2.5" style="width:30%" />
              </div>
            </div>
          </div>
          <template v-else>
            <label
              v-if="eligibleAmbassadors.length"
              class="flex items-center gap-3 px-4 py-2.5 bg-border-soft/50 border-b border-border-soft cursor-pointer hover:bg-border-soft transition-colors"
            >
              <input
                type="checkbox"
                class="accent-cyan w-4 h-4"
                :checked="allEligibleSelected"
                @change="toggleSelectAllEligible"
              />
              <span class="text-[12.5px] font-medium text-ink">Select all</span>
            </label>
            <label
              v-for="amb in eligibleAmbassadors"
              :key="amb.id"
              class="flex items-center gap-3 px-4 py-3 hover:bg-border-soft/40 cursor-pointer border-b border-border-soft last:border-b-0 transition-colors"
            >
              <input type="checkbox" :value="amb.id" v-model="selectedIds" class="accent-cyan w-4 h-4" />
              <div class="flex-1 min-w-0">
                <div class="text-[13px] font-medium text-ink tracking-[-0.005em]">{{ amb.name }}</div>
                <div class="text-[11px] text-text-muted">{{ amb.team_name ?? 'No team' }}</div>
              </div>
            </label>
            <div v-if="!eligibleAmbassadors.length" class="px-4 py-8 text-center text-[12.5px] text-text-muted">
              <template v-if="!withSalesAmbassadors.length">No confirmed sales for this month — nothing to pay out yet.</template>
              <template v-else>Everyone with sales this month already has a payout record.</template>
            </div>
          </template>
        </div>
      </div>
    </div>
    <template #footer>
      <button class="btn-secondary" @click="$emit('update:modelValue', false)">Cancel</button>
      <button
        class="btn-primary"
        :disabled="!selected.size || loading"
        @click="handleCreate"
      >{{ loading ? 'Creating…' : `Create ${selected.size} payout${selected.size === 1 ? '' : 's'}` }}</button>
    </template>
  </AppModal>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { formatMonthLabel } from '~/utils/dateFormat'

const props = defineProps<{ modelValue: boolean }>()
const emit  = defineEmits<{ 'update:modelValue': [boolean]; saved: [] }>()

const selectedMonth = ref('')
const selectedIds   = ref<number[]>([])
const selected      = computed(() => new Set(selectedIds.value))
const loading       = ref(false)
const loadingMonth  = ref(false)
const config        = useRuntimeConfig()
const auth          = useAuthStore()

const withSalesAmbassadors = ref<Array<{ id: number; name: string; team_name?: string | null }>>([])
const payoutsForMonth      = ref<Array<{ ambassador_id: number }>>([])

const { data: months } = useAPI('commissions/months')

const monthOpts = computed(() =>
  (months.value ?? []).map((m: any) => ({
    value: m.month,
    label: formatMonthLabel(m.month),
  }))
)

async function loadMonthContext(month: string) {
  if (!month) {
    withSalesAmbassadors.value = []
    payoutsForMonth.value      = []
    return
  }
  loadingMonth.value = true
  try {
    const [ambRes, payRes] = await Promise.all([
      fetch(`${config.public.apiBase}/commissions/ambassadors-for-month?${new URLSearchParams({ month }).toString()}`, {
        headers: { Authorization: `Bearer ${auth.token}` },
      }),
      fetch(`${config.public.apiBase}/payouts?${new URLSearchParams({ month }).toString()}`, {
        headers: { Authorization: `Bearer ${auth.token}` },
      }),
    ])
    const ambJson = await ambRes.json()
    const payJson = await payRes.json()
    if (!ambRes.ok) throw new Error(ambJson.message ?? 'Failed to load ambassadors')
    if (!payRes.ok) throw new Error(payJson.message ?? 'Failed to load payouts')
    withSalesAmbassadors.value = Array.isArray(ambJson.data) ? ambJson.data : []
    payoutsForMonth.value      = Array.isArray(payJson.data) ? payJson.data : []
  } catch {
    withSalesAmbassadors.value = []
    payoutsForMonth.value      = []
  } finally {
    loadingMonth.value = false
  }
}

const eligibleAmbassadors = computed(() => {
  if (!selectedMonth.value) return []
  const existingIds = new Set(payoutsForMonth.value.map((p) => p.ambassador_id))
  return withSalesAmbassadors.value.filter(
    (a) => a.name !== 'Johnny' && a.name !== 'Unassigned Sales' && !existingIds.has(a.id),
  )
})

const allEligibleSelected = computed(
  () =>
    eligibleAmbassadors.value.length > 0
    && selectedIds.value.length === eligibleAmbassadors.value.length
    && eligibleAmbassadors.value.every((a) => selectedIds.value.includes(a.id)),
)

function toggleSelectAllEligible() {
  if (allEligibleSelected.value) {
    selectedIds.value = []
    return
  }
  selectedIds.value = eligibleAmbassadors.value.map((a) => a.id)
}

watch(selectedMonth, (m) => {
  selectedIds.value = []
  loadMonthContext(m)
})

watch(
  () => props.modelValue,
  (open) => {
    if (!open) {
      selectedMonth.value = ''
      selectedIds.value   = []
      withSalesAmbassadors.value = []
      payoutsForMonth.value      = []
    }
  },
)

async function handleCreate() {
  if (!selected.value.size) return
  loading.value = true
  try {
    const items = [...selected.value].map(id => ({ ambassador_id: id, month: selectedMonth.value + '-01' }))
    await fetch(`${config.public.apiBase}/payouts/batch`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Authorization: `Bearer ${auth.token}` },
      body: JSON.stringify({ items }),
    })
    emit('update:modelValue', false)
    emit('saved')
  } finally {
    loading.value = false
  }
}
</script>
