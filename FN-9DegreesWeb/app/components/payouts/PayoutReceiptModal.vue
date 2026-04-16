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
        <div v-if="receipts.length === 0" class="text-[13px] text-gray-400 py-3 text-center border border-dashed border-[#E8E8EC] rounded-xl">
          No receipts attached yet.
        </div>
        <ul v-else class="border border-[#E8E8EC] rounded-xl divide-y divide-[#F0F0F0]">
          <li v-for="(r, i) in receipts" :key="i" class="flex items-center gap-3 px-3 py-2">
            <span class="flex-1 text-[13px] text-ink truncate">{{ r.name }}</span>
            <button class="text-[12px] text-[#00A0A6] hover:underline" @click="doDownload(i, r.name)">Download</button>
            <button class="text-[12px] text-red-400 hover:underline" :disabled="busy" @click="doDelete(i)">Delete</button>
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
          class="block w-full text-[13px] file:mr-3 file:rounded-lg file:border-0 file:bg-[#00C4CC12] file:px-3 file:py-1.5 file:text-[12px] file:font-semibold file:text-[#007a80]"
          @change="onFilePick"
        />
        <p v-if="errorMsg" class="text-[11px] text-red-500 mt-1">{{ errorMsg }}</p>
        <p class="text-[11px] text-gray-400 mt-1">PNG, JPG, or PDF. Max 10 MB. Up to 10 files per payout.</p>
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
