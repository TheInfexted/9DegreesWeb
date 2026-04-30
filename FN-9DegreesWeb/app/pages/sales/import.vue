<template>
  <NuxtLayout>
    <!-- Stage 1: Upload -->
    <template v-if="stage === 'upload'">
      <div class="max-w-xl mx-auto">
        <div class="mb-6">
          <p class="text-[10.5px] font-semibold uppercase tracking-[0.1em] text-text-muted mb-1">Sales · Import</p>
          <h1 class="text-[22px] font-semibold text-ink tracking-tightest">Import sales from PDF</h1>
          <p class="text-[13px] text-text-soft mt-1.5 max-w-prose">
            Upload an agent commission report PDF. Each parsed row becomes a draft sale you can review before committing.
          </p>
        </div>

        <div class="surface p-6 space-y-5">
          <div>
            <label class="field-label">PDF file</label>
            <label
              class="flex flex-col items-center justify-center gap-2 border-2 border-dashed rounded-xl p-9 cursor-pointer transition-all duration-150"
              :class="selectedFile
                ? 'border-cyan/60 bg-cyan-tint/40'
                : 'border-border hover:border-cyan/40 hover:bg-border-soft/40'"
              @dragover.prevent
              @drop.prevent="onDrop"
            >
              <input ref="fileInputRef" type="file" accept="application/pdf,.pdf" class="hidden" @change="onFileChange" />
              <template v-if="!selectedFile">
                <div class="w-10 h-10 rounded-lg bg-border-soft flex items-center justify-center text-text-muted">
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 13l3-3m0 0l3 3m-3-3v12M5 8a4 4 0 014-4h6.5a4 4 0 014 4v8a4 4 0 01-4 4H9a4 4 0 01-4-4V8z"/></svg>
                </div>
                <p class="text-[13px] text-text font-medium">Click or drag a PDF here</p>
                <p class="text-[11.5px] text-text-muted">Agent commission report only</p>
              </template>
              <template v-else>
                <div class="w-10 h-10 rounded-lg bg-cyan-tint flex items-center justify-center text-cyan-dark ring-1 ring-cyan/20">
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6M9 4h6a2 2 0 012 2v14a2 2 0 01-2 2H9a2 2 0 01-2-2V6a2 2 0 012-2z"/></svg>
                </div>
                <p class="text-[13px] font-medium text-ink">{{ selectedFile.name }}</p>
                <p class="text-[11.5px] text-text-muted tabular">{{ formatFileSize(selectedFile.size) }}</p>
              </template>
            </label>
          </div>

          <p
            v-if="uploadError"
            class="text-[12px] text-[#B83227] bg-[#FDF2F1] ring-1 ring-inset ring-[#F1D8D5] rounded-md px-3 py-2"
          >{{ uploadError }}</p>

          <div class="flex justify-end gap-2 pt-1">
            <NuxtLink to="/sales" class="btn-secondary text-[13px]">Cancel</NuxtLink>
            <button
              type="button"
              class="btn-primary text-[13px] inline-flex items-center gap-1.5"
              :disabled="!selectedFile || parsing"
              @click="doParse"
            >
              <span v-if="parsing" class="w-3.5 h-3.5 rounded-full border-2 border-white/30 border-t-white animate-spin" aria-hidden="true" />
              {{ parsing ? 'Parsing' : 'Parse PDF' }}
            </button>
          </div>
        </div>
      </div>
    </template>

    <!-- Stage 2: Preview -->
    <template v-else>
      <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
        <div>
          <p class="text-[10.5px] font-semibold uppercase tracking-[0.1em] text-text-muted mb-1">Sales · Import · Review</p>
          <h1 class="text-[20px] font-semibold text-ink tracking-tightest">Review import</h1>
          <p class="text-[13px] text-text-soft mt-1">
            Choose which ambassador each imported sale belongs to. Rows set to Skip don't need an ambassador.
          </p>
        </div>
        <div class="flex gap-2 shrink-0">
          <button type="button" class="btn-secondary text-[13px]" @click="stage = 'upload'">Back</button>
          <button
            type="button"
            class="btn-primary text-[13px] inline-flex items-center gap-1.5"
            :disabled="importCount === 0 || committing || missingAmbassadorOnImportRows"
            :title="missingAmbassadorOnImportRows ? 'Select an ambassador for every row you are importing.' : undefined"
            @click="doCommit"
          >
            <span v-if="committing" class="w-3.5 h-3.5 rounded-full border-2 border-white/30 border-t-white animate-spin" aria-hidden="true" />
            {{ committing ? 'Importing' : `Import ${importCount} row${importCount !== 1 ? 's' : ''}` }}
          </button>
        </div>
      </div>

      <!-- Summary cards -->
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
        <AppCard label="Ready" :value="summaryReady" :accent="false" />
        <AppCard label="Duplicates" :value="summaryDuplicates" :accent="false" />
        <AppCard label="File errors" :value="parseErrors.length" :accent="false" />
        <AppCard label="Will import" :value="importCount" />
      </div>

      <!-- Bulk dup actions -->
      <div
        v-if="summaryDuplicates > 0"
        class="bg-[#FDF6E7] ring-1 ring-inset ring-[#F0DCB1] rounded-lg px-4 py-3 mb-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"
      >
        <p class="text-[12px] text-[#9C6611] inline-flex items-start gap-2">
          <svg class="w-3.5 h-3.5 mt-0.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3m0 4h.01M5.062 19h13.876c1.54 0 2.502-1.667 1.732-3L13.732 5c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.722 3z"/></svg>
          {{ summaryDuplicates }} row{{ summaryDuplicates !== 1 ? 's' : '' }} matched an existing receipt. Choose how to handle them:
        </p>
        <div class="flex gap-2 shrink-0">
          <button type="button" class="btn-secondary text-[12px] py-1.5 px-3" @click="bulkDupAction('skip')">Skip all</button>
          <button type="button" class="btn-secondary text-[12px] py-1.5 px-3" @click="bulkDupAction('overwrite')">Overwrite all drafts</button>
        </div>
      </div>

      <!-- Parse errors -->
      <div
        v-if="parseErrors.length"
        class="bg-[#FDF2F1] ring-1 ring-inset ring-[#F1D8D5] rounded-lg px-4 py-3 mb-4"
      >
        <p class="text-[12px] font-semibold text-[#B83227] mb-2 inline-flex items-center gap-1.5">
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
          {{ parseErrors.length }} row{{ parseErrors.length !== 1 ? 's' : '' }} could not be parsed
        </p>
        <ul class="space-y-1">
          <li v-for="e in parseErrors" :key="e.line" class="text-[11px] text-[#B83227] font-mono truncate">
            Line {{ e.line }}: {{ e.text }} — {{ e.reason }}
          </li>
        </ul>
      </div>

      <!-- Preview table -->
      <AppTable :columns="columns" :rows="rows" :get-row-class="getRowClass">
        <template #default="{ row: rawRow }">
          <template v-for="row in [rawRow as ImportRow]" :key="row.receipt">
            <td class="px-4 py-2 text-[11px] font-mono text-text-muted whitespace-nowrap max-w-[140px] truncate" :title="row.receipt">
              {{ row.receipt }}
            </td>
            <td class="px-4 py-2 min-w-[160px]">
              <AppSelect
                v-model="row.ambassador_id"
                :options="ambassadorOpts"
                placeholder="Select ambassador"
                :disabled="!rowNeedsAmbassador(row)"
                class="text-[12px] py-1"
              />
            </td>
            <td class="px-4 py-2 text-[12px] text-text tabular">
              <input
                v-if="row.editing"
                v-model="row.date"
                type="date"
                class="field-input text-[12px] py-1 w-[130px]"
              />
              <span v-else>{{ row.date }}</span>
            </td>
            <td class="px-4 py-2 text-[12px]">
              <AppSelect
                v-if="row.editing"
                v-model="row.sale_type"
                :options="typeOpts"
                class="text-[12px] py-1"
              />
              <span v-else class="text-text">{{ row.sale_type }}</span>
            </td>
            <td class="px-4 py-2 text-[12px] text-text tabular">
              <input
                v-if="row.editing"
                v-model="row.table_number"
                type="text"
                class="field-input text-[12px] py-1 w-[80px]"
                placeholder="—"
              />
              <span v-else>{{ row.table_number || '—' }}</span>
            </td>
            <td class="px-4 py-2 text-[12px] text-right font-medium text-ink tabular">
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
            <td class="px-4 py-2">
              <AppBadge :variant="rowBadgeVariant(row)">{{ rowBadgeLabel(row) }}</AppBadge>
            </td>
            <td class="px-4 py-2">
              <div class="flex justify-end items-center gap-1">
                <template v-if="!row.editing">
                  <button v-if="row.decision !== 'skip'" class="act-btn" title="Edit" @click="row.editing = true">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536M16.5 3.75a2.121 2.121 0 113 3L7 19.25l-4 1 1-4L16.5 3.75z"/></svg>
                  </button>
                </template>
                <template v-else>
                  <button class="act-btn text-cyan-dark hover:bg-cyan-tint" title="Done" @click="row.editing = false">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.4" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                  </button>
                </template>
                <template v-if="isDupDraft(row)">
                  <button v-if="row.decision !== 'skip'" class="act-btn" title="Skip this row" @click="row.decision = 'skip'">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728L5.636 5.636"/></svg>
                  </button>
                  <button v-if="row.decision !== 'overwrite'" class="act-btn text-cyan-dark hover:bg-cyan-tint" title="Overwrite existing draft" @click="row.decision = 'overwrite'">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h5M20 20v-5h-5M5 9a8 8 0 0114-3m1 9a8 8 0 01-14 3"/></svg>
                  </button>
                </template>
                <template v-if="!isDup(row)">
                  <button v-if="row.decision !== 'skip'" class="act-btn" title="Skip this row" @click="row.decision = 'skip'">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728L5.636 5.636"/></svg>
                  </button>
                  <button v-if="row.decision === 'skip'" class="act-btn text-cyan-dark hover:bg-cyan-tint" title="Include this row" @click="row.decision = 'create'">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.4" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14M5 12h14"/></svg>
                  </button>
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
  decision: 'create' | 'overwrite' | 'skip'
  editing:  boolean
  ambassador_id: string
}

interface ParseError {
  line:   number
  text:   string
  reason: string
}

const stage = ref<'upload' | 'preview'>('upload')

const { data: ambassadors } = useAPI('ambassadors', { status: 'active' })

const selectedFile         = ref<File | null>(null)
const fileInputRef         = ref<HTMLInputElement | null>(null)
const uploadError          = ref<string | null>(null)
const parsing              = ref(false)

const ambassadorOpts = computed(() =>
  (ambassadors.value ?? []).map((a: any) => ({ value: String(a.id), label: a.name })),
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

const config = useRuntimeConfig()
const auth   = useAuthStore()
const toast  = useToast()

async function doParse() {
  if (!selectedFile.value) return
  uploadError.value = null
  parsing.value     = true
  try {
    const fd = new FormData()
    fd.append('file', selectedFile.value)

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
      decision: (r.duplicate_in_file || r.existing_sale !== null) ? 'skip' : 'create',
      editing:  false,
      ambassador_id: '',
    }))
    parseErrors.value = parsed.errors ?? []
    stage.value = 'preview'
  } catch (e: unknown) {
    uploadError.value = e instanceof Error ? e.message : 'Failed.'
  } finally {
    parsing.value = false
  }
}

const rows:        ReturnType<typeof ref<ImportRow[]>> = ref([])
const parseErrors  = ref<ParseError[]>([])
const committing   = ref(false)

const typeOpts = [
  { value: 'Table', label: 'Table' },
  { value: 'BGO',   label: 'BGO'   },
]

const columns = [
  { key: 'receipt',    label: 'Receipt'       },
  { key: 'ambassador', label: 'Ambassador'    },
  { key: 'date',     label: 'Date'          },
  { key: 'type',     label: 'Type'          },
  { key: 'table',    label: 'Table #'       },
  { key: 'gross',    label: 'Gross (RM)',   align: 'right' as const },
  { key: 'status',   label: 'Status'        },
  { key: 'actions',  label: ''              },
]

function isDup(row: ImportRow)        { return row.existing_sale !== null }
function isDupDraft(row: ImportRow)   { return row.existing_sale?.status === 'draft' }
function isDupLocked(row: ImportRow)  {
  return row.existing_sale !== null && row.existing_sale.status !== 'draft'
}

function rowNeedsAmbassador(row: ImportRow): boolean {
  if (row.duplicate_in_file) return false
  if (row.decision === 'skip') return false
  return true
}

const summaryDuplicates = computed(() => rows.value.filter(isDup).length)
const summaryReady      = computed(() => rows.value.filter(r => !isDup(r) && !r.duplicate_in_file).length)
const importCount       = computed(() =>
  rows.value.filter(r => !r.duplicate_in_file && r.decision !== 'skip').length,
)

const missingAmbassadorOnImportRows = computed(() =>
  rows.value.some(r => rowNeedsAmbassador(r) && (r.ambassador_id === '' || Number(r.ambassador_id) <= 0)),
)

function rowBadgeVariant(row: ImportRow): string {
  if (row.duplicate_in_file) return 'void'
  if (isDupLocked(row))      return 'void'
  if (isDupDraft(row)) {
    if (row.decision === 'skip')      return 'inactive'
    if (row.decision === 'overwrite') return 'confirmed'
    return 'unpaid'
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

function getRowClass(rawRow: unknown): string | undefined {
  const row = rawRow as ImportRow
  if (row.duplicate_in_file)                return 'bg-[#FDF2F1]'
  if (isDupLocked(row))                     return 'bg-[#FDF2F1] opacity-60'
  if (isDupDraft(row) && row.decision !== 'skip' && row.decision !== 'overwrite')
                                             return 'bg-[#FDF6E7]'
  if (row.decision === 'skip')              return 'opacity-40'
  if (rowNeedsAmbassador(row) && (row.ambassador_id === '' || Number(row.ambassador_id) <= 0))
                                            return 'ring-1 ring-inset ring-[#F0DCB1] bg-[#FDF6E7]/40'
  return undefined
}

function bulkDupAction(action: 'skip' | 'overwrite') {
  for (const row of rows.value) {
    if (!isDup(row)) continue
    if (action === 'overwrite' && isDupLocked(row)) continue
    row.decision = action
  }
}

async function doCommit() {
  if (importCount.value === 0) return
  committing.value = true
  try {
    const decisions = rows.value
      .filter(r => !r.duplicate_in_file)
      .map((r) => {
        const base = {
          action:       r.decision,
          receipt:      r.receipt,
          date:         r.date,
          sale_type:    r.sale_type,
          table_number: r.table_number,
          gross_amount: r.gross_amount,
        }
        if (r.decision === 'skip') {
          return base
        }
        return { ...base, ambassador_id: Number(r.ambassador_id) }
      })

    const res  = await fetch(`${config.public.apiBase}/sales/import/commit`, {
      method:  'POST',
      headers: {
        'Content-Type': 'application/json',
        Authorization:  `Bearer ${auth.token}`,
      },
      body: JSON.stringify({ decisions }),
    })
    const json = await res.json()
    if (!res.ok) throw new Error(json.message ?? 'Import failed.')

    const result  = json.data ?? json
    const created = result.created ?? 0
    const updated = result.updated ?? 0
    const skipped = result.skipped ?? 0
    const failed  = result.failed ?? []

    const detail = `Created ${created} · Updated ${updated} · Skipped ${skipped}`

    if (failed.length) {
      const lines = failed.slice(0, 4).map((f: any) => `• ${f.receipt}: ${f.message}`).join('\n')
      const more  = failed.length > 4 ? `\n…and ${failed.length - 4} more` : ''
      toast.error(`${failed.length} row${failed.length === 1 ? '' : 's'} failed`, `${detail}\n\n${lines}${more}`)
    } else {
      toast.success('Import complete', detail)
    }
    navigateTo('/sales')
  } catch (e: unknown) {
    toast.error('Import failed', e instanceof Error ? e.message : 'Please try again.')
  } finally {
    committing.value = false
  }
}
</script>
