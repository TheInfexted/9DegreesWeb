<template>
  <AppModal :model-value="modelValue" title="Create Payout" size="md" @update:model-value="$emit('update:modelValue', $event)">
    <div class="space-y-4">
      <div>
        <label class="field-label">Month</label>
        <AppSelect v-model="selectedMonth" :options="monthOpts" placeholder="Select month" />
      </div>
      <div v-if="selectedMonth">
        <label class="field-label">Ambassadors ({{ selected.size }} selected)</label>
        <p class="text-[11px] text-gray-500 mb-2">Only ambassadors with confirmed sales in this month. Johnny and Unassigned Sales are excluded.</p>
        <div class="border border-[#E8E8EC] rounded-xl overflow-hidden max-h-64 overflow-y-auto">
          <div v-if="loadingMonth" class="px-4 py-6 text-center text-[13px] text-gray-400">Loading…</div>
          <template v-else>
            <label
              v-for="amb in eligibleAmbassadors"
              :key="amb.id"
              class="flex items-center gap-3 px-4 py-3 hover:bg-gray-50 cursor-pointer border-b border-[#F0F0F0] last:border-b-0"
            >
              <input type="checkbox" :value="amb.id" v-model="selectedIds" class="accent-[#00C4CC]" />
              <div class="flex-1">
                <div class="text-[13px] font-medium text-ink">{{ amb.name }}</div>
                <div class="text-[11px] text-gray-400">{{ amb.team_name ?? 'No team' }}</div>
              </div>
            </label>
            <div v-if="!eligibleAmbassadors.length" class="px-4 py-6 text-center text-[13px] text-gray-400">
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
      >{{ loading ? 'Creating…' : `Create ${selected.size} Payout(s)` }}</button>
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
