<template>
  <div class="bg-white border border-[#E8E8EC] rounded-2xl overflow-hidden shadow-sm">
    <div class="overflow-x-auto">
      <table class="w-full">
        <thead>
          <tr class="bg-[#FAFAFA] border-b border-[#F0F0F0]">
            <th
              v-for="col in columns"
              :key="col.key"
              class="px-4 py-2.5 text-[10px] font-bold uppercase tracking-wide text-gray-300 text-left whitespace-nowrap"
              :class="col.align === 'right' ? 'text-right' : ''"
            >{{ col.label }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="loading">
            <td :colspan="columns.length" class="px-4 py-8 text-center text-[13px] text-gray-400">Loading…</td>
          </tr>
          <tr v-else-if="!rows?.length">
            <td :colspan="columns.length" class="px-4 py-8 text-center text-[13px] text-gray-400">No records found.</td>
          </tr>
          <tr
            v-for="(row, i) in rows"
            :key="i"
            class="border-b border-[#F8F8F8] hover:bg-[#FAFCFC] transition-colors last:border-b-0"
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
}>()
</script>
