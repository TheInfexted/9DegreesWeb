<template>
  <AppModal :model-value="modelValue" :title="isEdit ? 'Edit Sale' : 'New Sale'" size="md" @update:model-value="$emit('update:modelValue', $event)">
    <form class="space-y-4" @submit.prevent="handleSubmit">
      <div class="grid grid-cols-2 gap-3">
        <div class="col-span-2">
          <label class="field-label">Ambassador</label>
          <AppSelect v-model="form.ambassador_id" :options="ambassadorOpts" placeholder="Select ambassador" />
        </div>
        <div>
          <label class="field-label">Date</label>
          <input v-model="form.date" type="date" class="field-input w-full" required />
        </div>
        <div>
          <label class="field-label">Sale Type</label>
          <AppSelect v-model="form.sale_type" :options="[{value:'Table',label:'Table'},{value:'BGO',label:'BGO'}]" />
        </div>
        <div class="col-span-2">
          <label class="field-label">Table Number</label>
          <input
            v-model="form.table_number"
            type="text"
            class="field-input w-full"
            placeholder="e.g. T04"
            :required="form.sale_type === 'Table'"
          />
          <p v-if="form.sale_type === 'BGO'" class="mt-1 text-[11px] text-gray-400">Optional for BGO</p>
        </div>
        <div class="col-span-2">
          <label class="field-label">Gross Amount (RM)</label>
          <input v-model="form.gross_amount" type="number" step="0.01" min="0.01" class="field-input w-full" required />
        </div>
        <div class="col-span-2">
          <label class="field-label">Remarks</label>
          <input v-model="form.remarks" type="text" class="field-input w-full" placeholder="Optional" />
        </div>
      </div>
      <p v-if="error" class="text-[12px] text-red-500 bg-red-50 rounded-lg px-3 py-2">{{ error }}</p>
    </form>
    <template #footer>
      <button type="button" class="btn-secondary" @click="$emit('update:modelValue', false)">Cancel</button>
      <button type="button" class="btn-primary" :disabled="loading" @click="handleSubmit">
        {{ loading ? 'Saving…' : isEdit ? 'Save Changes' : 'Create Sale' }}
      </button>
    </template>
  </AppModal>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue'

const props = defineProps<{
  modelValue: boolean
  sale?: any
  ambassadors: any[]
}>()

const emit = defineEmits<{
  'update:modelValue': [boolean]
  saved: []
}>()

const isEdit = computed(() => !!props.sale)
const form   = ref({ ambassador_id: '', date: '', sale_type: 'Table', table_number: '', gross_amount: '', remarks: '' })
const error  = ref<string | null>(null)
const config = useRuntimeConfig()
const auth   = useAuthStore()
const loading = ref(false)

const ambassadorOpts = computed(() =>
  props.ambassadors
    .filter((a: any) => a.status === 'active')
    .map((a: any) => ({ value: a.id, label: a.name }))
)

watch(() => props.sale, (s) => {
  if (s) form.value = { ambassador_id: s.ambassador_id, date: s.date, sale_type: s.sale_type, table_number: s.table_number ?? '', gross_amount: s.gross_amount, remarks: s.remarks ?? '' }
  else form.value = { ambassador_id: '', date: new Date().toISOString().slice(0, 10), sale_type: 'Table', table_number: '', gross_amount: '', remarks: '' }
}, { immediate: true })

async function handleSubmit() {
  loading.value = true
  error.value   = null
  try {
    const url    = isEdit.value ? `${config.public.apiBase}/sales/${props.sale.id}` : `${config.public.apiBase}/sales`
    const method = isEdit.value ? 'PUT' : 'POST'
    const res    = await fetch(url, {
      method,
      headers: { 'Content-Type': 'application/json', Authorization: `Bearer ${auth.token}` },
      body: JSON.stringify({
        ...form.value,
        table_number: form.value.table_number.trim() === '' ? null : form.value.table_number.trim(),
      }),
    })
    const json = await res.json()
    if (!res.ok) throw new Error(json.message ?? 'Failed to save sale.')
    emit('update:modelValue', false)
    emit('saved')
  } catch (e: unknown) {
    error.value = e instanceof Error ? e.message : 'Failed.'
  } finally {
    loading.value = false
  }
}
</script>
