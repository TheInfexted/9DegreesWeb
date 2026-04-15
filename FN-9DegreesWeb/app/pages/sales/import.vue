<template>
  <NuxtLayout>
    <!-- Stage 1: Upload -->
    <template v-if="stage === 'upload'">
      <div class="max-w-lg mx-auto">
        <div class="mb-6">
          <h1 class="text-[20px] font-bold text-ink">Import Sales from PDF</h1>
          <p class="text-[13px] text-gray-500 mt-1">
            Upload an agent commission report PDF. Rows will be created as draft sales.
          </p>
        </div>

        <div class="bg-white border border-[#E8E8EC] rounded-2xl p-6 shadow-sm space-y-5">
          <div>
            <label class="field-label">Ambassador</label>
            <AppSelect v-model="selectedAmbassadorId" :options="ambassadorOpts" placeholder="Select ambassador" />
          </div>

          <div>
            <label class="field-label">PDF File</label>
            <label
              class="flex flex-col items-center justify-center gap-2 border-2 border-dashed border-[#E8E8EC] rounded-xl p-8 cursor-pointer hover:border-[#00C4CC] transition-colors"
              :class="{ 'border-[#00C4CC] bg-[#f0fdfd]': selectedFile }"
              @dragover.prevent
              @drop.prevent="onDrop"
            >
              <input ref="fileInputRef" type="file" accept="application/pdf,.pdf" class="hidden" @change="onFileChange" />
              <span v-if="!selectedFile" class="text-[13px] text-gray-400">Click or drag a PDF here</span>
              <template v-else>
                <span class="text-[13px] font-medium text-ink">{{ selectedFile.name }}</span>
                <span class="text-[11px] text-gray-400">{{ formatFileSize(selectedFile.size) }}</span>
              </template>
            </label>
          </div>

          <p v-if="uploadError" class="text-[12px] text-red-500 bg-red-50 rounded-lg px-3 py-2">{{ uploadError }}</p>

          <div class="flex justify-end gap-3 pt-2">
            <NuxtLink to="/sales" class="btn-secondary text-[13px]">Cancel</NuxtLink>
            <button
              type="button"
              class="btn-primary text-[13px]"
              :disabled="!selectedAmbassadorId || !selectedFile || parsing"
              @click="doParse"
            >
              {{ parsing ? 'Parsing…' : 'Parse PDF' }}
            </button>
          </div>
        </div>
      </div>
    </template>

    <!-- Stage 2: Preview -->
    <template v-else>
      <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 class="text-[20px] font-bold text-ink">Review Import</h1>
          <p class="text-[13px] text-gray-500">
            Importing for <span class="font-medium text-ink">{{ selectedAmbassadorName }}</span>
          </p>
        </div>
        <div class="flex gap-2 shrink-0">
          <button type="button" class="btn-secondary text-[13px]" @click="stage = 'upload'">Back</button>
          <button
            type="button"
            class="btn-primary text-[13px]"
            :disabled="importCount === 0 || committing"
            @click="doCommit"
          >
            {{ committing ? 'Importing…' : `Import ${importCount} row${importCount !== 1 ? 's' : ''}` }}
          </button>
        </div>
      </div>

      <!-- Summary cards -->
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
        <AppCard label="Ready" :value="summaryReady" />
        <AppCard label="Duplicates" :value="summaryDuplicates" />
        <AppCard label="File errors" :value="parseErrors.length" />
        <AppCard label="Will import" :value="importCount" />
      </div>

      <!-- Bulk dup actions -->
      <div v-if="summaryDuplicates > 0" class="bg-amber-50 border border-amber-200 rounded-xl px-4 py-3 mb-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <p class="text-[12px] text-amber-800">
          {{ summaryDuplicates }} row{{ summaryDuplicates !== 1 ? 's' : '' }} matched an existing receipt. Choose how to handle:
        </p>
        <div class="flex gap-2 shrink-0">
          <button type="button" class="btn-secondary text-[12px] py-1.5 px-3" @click="bulkDupAction('skip')">Skip all</button>
          <button type="button" class="btn-secondary text-[12px] py-1.5 px-3" @click="bulkDupAction('overwrite')">Overwrite all drafts</button>
        </div>
      </div>

      <!-- Parse errors -->
      <div v-if="parseErrors.length" class="bg-red-50 border border-red-200 rounded-xl px-4 py-3 mb-4">
        <p class="text-[12px] font-semibold text-red-700 mb-2">{{ parseErrors.length }} row{{ parseErrors.length !== 1 ? 's' : '' }} could not be parsed:</p>
        <ul class="space-y-1">
          <li v-for="e in parseErrors" :key="e.line" class="text-[11px] text-red-600 font-mono truncate">
            Line {{ e.line }}: {{ e.text }} — {{ e.reason }}
          </li>
        </ul>
      </div>

      <!-- Preview table -->
      <AppTable :columns="columns" :rows="rows" :get-row-class="getRowClass">
        <template #default="{ row: rawRow }">
          <template v-for="row in [rawRow as ImportRow]" :key="row.receipt">
            <!-- Receipt (read-only) -->
            <td class="px-4 py-2 text-[11px] font-mono text-gray-400 whitespace-nowrap max-w-[140px] truncate" :title="row.receipt">
              {{ row.receipt }}
            </td>

            <!-- Date -->
            <td class="px-4 py-2 text-[12px] text-gray-700">
              <input
                v-if="row.editing"
                v-model="row.date"
                type="date"
                class="field-input text-[12px] py-1 w-[130px]"
              />
              <span v-else>{{ row.date }}</span>
            </td>

            <!-- Sale type -->
            <td class="px-4 py-2 text-[12px]">
              <AppSelect
                v-if="row.editing"
                v-model="row.sale_type"
                :options="typeOpts"
                class="text-[12px] py-1"
              />
              <span v-else class="text-gray-700">{{ row.sale_type }}</span>
            </td>

            <!-- Table # -->
            <td class="px-4 py-2 text-[12px] text-gray-700">
              <input
                v-if="row.editing"
                v-model="row.table_number"
                type="text"
                class="field-input text-[12px] py-1 w-[80px]"
                placeholder="—"
              />
              <span v-else>{{ row.table_number || '—' }}</span>
            </td>

            <!-- Gross -->
            <td class="px-4 py-2 text-[12px] text-right font-semibold text-ink">
              <input
                v-if="row.editing"
                v-model.number="row.gross_amount"
                type="number"
                step="0.01"
                min="0.01"
                class="field-input text-[12px] py-1 w-[100px] text-right"
              />
              <span v-else>{{ formatRM(row.gross_amount) }}</span>
            </td>

            <!-- Status pill -->
            <td class="px-4 py-2">
              <AppBadge :variant="rowBadgeVariant(row)">{{ rowBadgeLabel(row) }}</AppBadge>
            </td>

            <!-- Actions -->
            <td class="px-4 py-2">
              <div class="flex justify-end items-center gap-1">
                <!-- Edit toggle -->
                <template v-if="!row.editing">
                  <button
                    v-if="row.decision !== 'skip'"
                    class="act-btn"
                    title="Edit"
                    @click="row.editing = true"
                  >✎</button>
                </template>
                <template v-else>
                  <button class="act-btn text-[#007a80]" title="Done" @click="row.editing = false">✓</button>
                </template>

                <!-- Dup-draft: Skip / Overwrite -->
                <template v-if="isDupDraft(row)">
                  <button
                    v-if="row.decision !== 'skip'"
                    class="act-btn text-gray-400"
                    title="Skip this row"
                    @click="row.decision = 'skip'"
                  >⊘</button>
                  <button
                    v-if="row.decision !== 'overwrite'"
                    class="act-btn text-[#007a80]"
                    title="Overwrite existing draft"
                    @click="row.decision = 'overwrite'"
                  >⟳</button>
                </template>

                <!-- Ready: toggle exclude -->
                <template v-if="!isDup(row)">
                  <button
                    v-if="row.decision !== 'skip'"
                    class="act-btn text-gray-400"
                    title="Skip this row"
                    @click="row.decision = 'skip'"
                  >⊘</button>
                  <button
                    v-if="row.decision === 'skip'"
                    class="act-btn text-[#007a80]"
                    title="Include this row"
                    @click="row.decision = 'create'"
                  >+</button>
                </template>
              </div>
            </td>
          </template>
        </template>
      </AppTable>
    </template>
  </NuxtLayout>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { formatRM } from '~/utils/currency'

definePageMeta({ middleware: 'auth' })

/* ------------------------------------------------------------------ */
/* Types                                                                */
/* ------------------------------------------------------------------ */
interface ExistingSale {
  id: number
  status: 'draft' | 'confirmed' | 'void'
}

interface ImportRow {
  receipt:          string
  date:             string
  sale_type:        'Table' | 'BGO'
  table_number:     string | null
  gross_amount:     number
  duplicate_in_file: boolean
  existing_sale:    ExistingSale | null
  // mutable preview state
  decision: 'create' | 'overwrite' | 'skip'
  editing:  boolean
}

interface ParseError {
  line:   number
  text:   string
  reason: string
}

/* ------------------------------------------------------------------ */
/* Stage control                                                        */
/* ------------------------------------------------------------------ */
const stage = ref<'upload' | 'preview'>('upload')

/* ------------------------------------------------------------------ */
/* Stage 1 — upload                                                    */
/* ------------------------------------------------------------------ */
const { data: ambassadors } = useAPI('ambassadors', { status: 'active' })

const selectedAmbassadorId = ref<string>('')
const selectedFile         = ref<File | null>(null)
const fileInputRef         = ref<HTMLInputElement | null>(null)
const uploadError          = ref<string | null>(null)
const parsing              = ref(false)

const ambassadorOpts = computed(() =>
  (ambassadors.value ?? []).map((a: any) => ({ value: String(a.id), label: a.name })),
)

const selectedAmbassadorName = computed(
  () => ambassadorOpts.value.find(o => o.value === selectedAmbassadorId.value)?.label ?? '',
)

function onFileChange(e: Event) {
  const input = e.target as HTMLInputElement
  selectedFile.value = input.files?.[0] ?? null
  uploadError.value  = null
}

function onDrop(e: DragEvent) {
  const file = e.dataTransfer?.files?.[0]
  if (!file) return
  selectedFile.value = file
  uploadError.value  = null
}

function formatFileSize(bytes: number): string {
  if (bytes < 1024) return bytes + ' B'
  if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB'
  return (bytes / (1024 * 1024)).toFixed(1) + ' MB'
}

/* ------------------------------------------------------------------ */
/* Stage 1 → 2: parse                                                  */
/* ------------------------------------------------------------------ */
const config = useRuntimeConfig()
const auth   = useAuthStore()

async function doParse() {
  if (!selectedFile.value || !selectedAmbassadorId.value) return
  uploadError.value = null
  parsing.value     = true
  try {
    const fd = new FormData()
    fd.append('file', selectedFile.value)
    fd.append('ambassador_id', selectedAmbassadorId.value)

    const res  = await fetch(`${config.public.apiBase}/sales/import/parse`, {
      method:  'POST',
      headers: { Authorization: `Bearer ${auth.token}` },
      body:    fd,
    })
    const json = await res.json()
    if (!res.ok) throw new Error(json.message ?? 'Parse failed.')

    const parsed = json.data ?? json
    rows.value   = (parsed.rows as any[]).map(r => ({
      ...r,
      // duplicate_in_file rows are always skipped — they cannot be imported.
      // existing_sale rows default to skip, requiring explicit user decision.
      decision: (r.duplicate_in_file || r.existing_sale !== null) ? 'skip' : 'create',
      editing:  false,
    }))
    parseErrors.value = parsed.errors ?? []
    stage.value = 'preview'
  } catch (e: unknown) {
    uploadError.value = e instanceof Error ? e.message : 'Failed.'
  } finally {
    parsing.value = false
  }
}

/* ------------------------------------------------------------------ */
/* Stage 2 — preview                                                   */
/* ------------------------------------------------------------------ */
const rows:        ReturnType<typeof ref<ImportRow[]>> = ref([])
const parseErrors  = ref<ParseError[]>([])
const committing   = ref(false)

const typeOpts = [
  { value: 'Table', label: 'Table' },
  { value: 'BGO',   label: 'BGO'   },
]

const columns = [
  { key: 'receipt',  label: 'Receipt'       },
  { key: 'date',     label: 'Date'          },
  { key: 'type',     label: 'Type'          },
  { key: 'table',    label: 'Table #'       },
  { key: 'gross',    label: 'Gross (RM)',   align: 'right' as const },
  { key: 'status',   label: 'Status'        },
  { key: 'actions',  label: ''              },
]

/* Helpers */
function isDup(row: ImportRow)        { return row.existing_sale !== null }
function isDupDraft(row: ImportRow)   { return row.existing_sale?.status === 'draft' }
function isDupLocked(row: ImportRow)  {
  return row.existing_sale !== null && row.existing_sale.status !== 'draft'
}

/* Summary counts */
const summaryDuplicates = computed(() => rows.value.filter(isDup).length)
const summaryReady      = computed(() => rows.value.filter(r => !isDup(r) && !r.duplicate_in_file).length)
const importCount       = computed(() =>
  rows.value.filter(r => !r.duplicate_in_file && r.decision !== 'skip').length,
)

/* Row badge */
function rowBadgeVariant(row: ImportRow): string {
  if (row.duplicate_in_file) return 'void'
  if (isDupLocked(row))      return 'void'
  if (isDupDraft(row)) {
    if (row.decision === 'skip')      return 'inactive'
    if (row.decision === 'overwrite') return 'confirmed'
    return 'unpaid'  // amber — pending decision
  }
  if (row.decision === 'skip') return 'inactive'
  return 'draft'
}

function rowBadgeLabel(row: ImportRow): string {
  if (row.duplicate_in_file) return 'File error'
  if (isDupLocked(row))      return `Locked (${row.existing_sale!.status})`
  if (isDupDraft(row)) {
    if (row.decision === 'skip')      return 'Skip'
    if (row.decision === 'overwrite') return 'Overwrite'
    return 'Duplicate'
  }
  if (row.decision === 'skip') return 'Skip'
  return 'Ready'
}

/* Row coloring via getRowClass */
function getRowClass(rawRow: unknown): string | undefined {
  const row = rawRow as ImportRow
  if (row.duplicate_in_file)                return 'bg-red-50'
  if (isDupLocked(row))                     return 'bg-red-50 opacity-60'
  if (isDupDraft(row) && row.decision !== 'skip' && row.decision !== 'overwrite')
                                             return 'bg-amber-50'
  if (row.decision === 'skip')              return 'opacity-40'
  return undefined
}

/* Bulk actions */
function bulkDupAction(action: 'skip' | 'overwrite') {
  for (const row of rows.value) {
    if (!isDup(row)) continue
    if (action === 'overwrite' && isDupLocked(row)) continue // can't overwrite confirmed/void
    row.decision = action
  }
}

/* Commit */
async function doCommit() {
  if (importCount.value === 0) return
  committing.value = true
  try {
    const decisions = rows.value
      .filter(r => !r.duplicate_in_file)
      .map(r => ({
        action:       r.decision,
        receipt:      r.receipt,
        date:         r.date,
        sale_type:    r.sale_type,
        table_number: r.table_number,
        gross_amount: r.gross_amount,
      }))

    const res  = await fetch(`${config.public.apiBase}/sales/import/commit`, {
      method:  'POST',
      headers: {
        'Content-Type': 'application/json',
        Authorization:  `Bearer ${auth.token}`,
      },
      body: JSON.stringify({
        ambassador_id: Number(selectedAmbassadorId.value),
        decisions,
      }),
    })
    const json = await res.json()
    if (!res.ok) throw new Error(json.message ?? 'Import failed.')

    const result  = json.data ?? json
    const created = result.created ?? 0
    const updated = result.updated ?? 0
    const skipped = result.skipped ?? 0
    const failed  = result.failed ?? []

    let msg = `Import complete. Created: ${created}, Updated: ${updated}, Skipped: ${skipped}.`
    if (failed.length) {
      msg += `\n\n${failed.length} row(s) failed:\n` + failed.map((f: any) => `• ${f.receipt}: ${f.message}`).join('\n')
    }
    window.alert(msg)
    navigateTo('/sales')
  } catch (e: unknown) {
    window.alert(e instanceof Error ? e.message : 'Import failed.')
  } finally {
    committing.value = false
  }
}
</script>
