<template>
  <div class="surface overflow-hidden">
    <div class="overflow-x-auto">
      <table class="w-full">
        <thead>
          <tr class="bg-border-soft/50 border-b border-border">
            <th
              v-for="col in columns"
              :key="col.key"
              class="px-4 py-3 text-[10.5px] font-semibold uppercase tracking-[0.08em] text-text-muted text-left whitespace-nowrap"
              :class="[col.align === 'right' ? 'text-right tabular' : '']"
            >{{ col.label }}</th>
          </tr>
        </thead>
        <tbody>
          <template v-if="loading">
            <tr v-for="n in skeletonRows" :key="`sk-${n}`" class="border-b border-border-soft last:border-b-0">
              <td v-for="col in columns" :key="col.key" class="px-4 py-3.5">
                <div class="skeleton h-3.5" :style="{ width: col.align === 'right' ? '60%' : '70%', marginLeft: col.align === 'right' ? 'auto' : '0' }" />
              </td>
            </tr>
          </template>
          <tr v-else-if="!rows?.length">
            <td :colspan="columns.length" class="px-4 py-12 text-center">
              <div class="flex flex-col items-center gap-2">
                <div class="w-10 h-10 rounded-full bg-border-soft flex items-center justify-center text-text-muted">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 17H5a2 2 0 01-2-2V5a2 2 0 012-2h4m6 18h4a2 2 0 002-2V5a2 2 0 00-2-2h-4m-6 0v18m6-18v18"/>
                  </svg>
                </div>
                <p class="text-[13px] text-text-soft">No records found</p>
                <p v-if="emptyHint" class="text-[12px] text-text-muted">{{ emptyHint }}</p>
              </div>
            </td>
          </tr>
          <tr
            v-for="(row, i) in rows"
            v-else
            :key="i"
            class="border-b border-border-soft hover:bg-border-soft/40 transition-colors duration-100 last:border-b-0"
            :class="getRowClass?.(row, i)"
          >
            <slot :row="row" />
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup lang="ts">
defineProps<{
  columns: { key: string; label: string; align?: 'left' | 'right' }[]
  rows: unknown[] | null
  loading?: boolean
  /** Optional hint shown under the empty state. */
  emptyHint?: string
  /** Optional per-row classes (e.g. highlight). */
  getRowClass?: (row: unknown, index: number) => string | Record<string, boolean> | undefined
}>()

const skeletonRows = 5
</script>
