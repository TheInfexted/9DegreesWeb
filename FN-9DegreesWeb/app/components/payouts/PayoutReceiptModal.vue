<template>
  <AppModal
    :model-value="modelValue"
    :title="payout ? `Receipts — ${payout.ambassador_name}` : 'Receipts'"
    size="md"
    @update:model-value="$emit('update:modelValue', $event)"
  >
    <div class="space-y-4">
      <!-- Existing receipts -->
      <div>
        <label class="field-label">Attached receipts</label>
        <div
          v-if="receipts.length === 0"
          class="text-[12.5px] text-text-muted py-6 text-center border border-dashed border-border rounded-lg bg-border-soft/30"
        >
          No receipts attached yet
        </div>
        <ul v-else class="border border-border rounded-lg divide-y divide-border-soft bg-white overflow-hidden">
          <li v-for="(r, i) in receipts" :key="i" class="flex items-center gap-3 px-3 py-2.5">
            <svg class="w-3.5 h-3.5 text-text-muted shrink-0" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6M9 4h6a2 2 0 012 2v14l-3-2-3 2-3-2-3 2V6a2 2 0 012-2z"/></svg>
            <span class="flex-1 text-[12.5px] text-ink truncate">{{ r.name }}</span>
            <button class="text-[11.5px] font-medium text-cyan-dark hover:underline underline-offset-2" @click="doDownload(i, r.name)">Download</button>
            <button class="text-[11.5px] font-medium text-[#DC4438] hover:underline underline-offset-2 disabled:opacity-50" :disabled="busy" @click="doDelete(i)">Delete</button>
          </li>
        </ul>
      </div>

      <!-- Upload -->
      <div>
        <label class="field-label">Attach new receipt</label>
        <input
          ref="fileInput"
          type="file"
          accept=".png,.jpg,.jpeg,.pdf,image/png,image/jpeg,application/pdf"
          class="block w-full text-[12.5px] text-text-soft
                 file:mr-3 file:rounded-md file:border-0
                 file:bg-cyan-tint file:px-3 file:py-1.5
                 file:text-[12px] file:font-semibold file:text-cyan-dark
                 hover:file:bg-cyan/15 file:cursor-pointer file:transition-colors"
          @change="onFilePick"
        />
        <p
          v-if="errorMsg"
          class="text-[11.5px] text-[#B83227] mt-2 inline-flex items-center gap-1.5"
        >
          <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
          {{ errorMsg }}
        </p>
        <p class="text-[11px] text-text-muted mt-2">PNG, JPG, or PDF. Up to 10 MB · 10 files per payout.</p>
      </div>
    </div>

    <template #footer>
      <button class="btn-secondary" @click="$emit('update:modelValue', false)">Close</button>
      <button class="btn-primary" :disabled="!pendingFile || busy" @click="doUpload">
        {{ busy ? 'Uploading…' : 'Upload' }}
      </button>
    </template>
  </AppModal>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'

interface ReceiptEntry { path: string; name: string }
interface PayoutRow { id: number; ambassador_name: string; receipt_paths?: string | null }

const props = defineProps<{ modelValue: boolean; payout: PayoutRow | null }>()
const emit  = defineEmits<{ 'update:modelValue': [boolean]; updated: [] }>()

const config = useRuntimeConfig()
const auth   = useAuthStore()

const fileInput   = ref<HTMLInputElement | null>(null)
const pendingFile = ref<File | null>(null)
const busy        = ref(false)
const errorMsg    = ref('')

const receipts = computed<ReceiptEntry[]>(() => {
  const raw = props.payout?.receipt_paths
  if (!raw) return []
  try {
    const parsed = JSON.parse(raw)
    return Array.isArray(parsed) ? parsed : []
  } catch {
    return []
  }
})

function onFilePick(ev: Event) {
  errorMsg.value = ''
  const target = ev.target as HTMLInputElement
  const file   = target.files?.[0] ?? null
  if (!file) { pendingFile.value = null; return }
  if (file.size > 10 * 1024 * 1024) {
    errorMsg.value = 'File must be 10 MB or smaller.'
    pendingFile.value = null
    return
  }
  pendingFile.value = file
}

async function doUpload() {
  if (!props.payout || !pendingFile.value) return
  busy.value = true
  errorMsg.value = ''
  try {
    const form = new FormData()
    form.append('receipt', pendingFile.value)
    const res = await fetch(`${config.public.apiBase}/payouts/${props.payout.id}/receipt`, {
      method: 'POST',
      headers: { Authorization: `Bearer ${auth.token}` },
      body: form,
    })
    if (!res.ok) {
      const body = await res.json().catch(() => null)
      throw new Error(body?.message ?? 'Upload failed.')
    }
    pendingFile.value = null
    if (fileInput.value) fileInput.value.value = ''
    emit('updated')
  } catch (e: any) {
    errorMsg.value = e?.message ?? 'Upload failed.'
  } finally {
    busy.value = false
  }
}

async function doDelete(index: number) {
  if (!props.payout) return
  busy.value = true
  try {
    await fetch(`${config.public.apiBase}/payouts/${props.payout.id}/receipt/${index}`, {
      method: 'DELETE',
      headers: { Authorization: `Bearer ${auth.token}` },
    })
    emit('updated')
  } finally {
    busy.value = false
  }
}

async function doDownload(index: number, filename: string) {
  if (!props.payout) return
  const res = await fetch(`${config.public.apiBase}/payouts/${props.payout.id}/receipt/${index}`, {
    headers: { Authorization: `Bearer ${auth.token}` },
  })
  if (!res.ok) return
  const blob = await res.blob()
  const url  = URL.createObjectURL(blob)
  const a    = document.createElement('a')
  a.href     = url
  a.download = filename
  a.click()
  setTimeout(() => URL.revokeObjectURL(url), 100)
}
</script>
