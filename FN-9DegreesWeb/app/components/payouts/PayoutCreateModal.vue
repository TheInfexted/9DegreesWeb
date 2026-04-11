<template>
  <AppModal :model-value="modelValue" title="Create Payout" size="md" @update:model-value="$emit('update:modelValue', $event)">
    <div class="space-y-4">
      <div>
        <label class="field-label">Month</label>
        <AppSelect v-model="selectedMonth" :options="monthOpts" placeholder="Select month" />
      </div>
      <div v-if="selectedMonth">
        <label class="field-label">Ambassadors ({{ selected.size }} selected)</label>
        <div class="border border-[#E8E8EC] rounded-xl overflow-hidden max-h-64 overflow-y-auto">
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
            No ambassadors with unpaid commissions this month.
          </div>
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

const props = defineProps<{ modelValue: boolean }>()
const emit  = defineEmits<{ 'update:modelValue': [boolean]; saved: [] }>()

const selectedMonth = ref('')
const selectedIds   = ref<number[]>([])
const selected      = computed(() => new Set(selectedIds.value))
const loading       = ref(false)
const config        = useRuntimeConfig()
const auth          = useAuthStore()

const { data: months }      = useAPI('commissions/months')
const { data: ambassadors } = useAPI('ambassadors', { status: 'active' })
const { data: payouts }     = useAPI('payouts', computed(() => ({ month: selectedMonth.value })))

const monthOpts = computed(() => (months.value ?? []).map((m: any) => ({ value: m.month, label: m.month })))

const eligibleAmbassadors = computed(() => {
  if (!selectedMonth.value) return []
  const existingIds = new Set((payouts.value ?? []).map((p: any) => p.ambassador_id))
  return (ambassadors.value ?? []).filter((a: any) =>
    a.name !== 'Johnny' && a.name !== 'Unassigned Sales' && !existingIds.has(a.id)
  )
})

watch(selectedMonth, () => { selectedIds.value = [] })

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
