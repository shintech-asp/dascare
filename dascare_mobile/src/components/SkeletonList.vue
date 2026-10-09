<template>
  <!-- Grey placeholder shapes while data loads (instead of a spinner). -->
  <div :class="[ui.card, 'overflow-hidden']" aria-busy="true" aria-label="Loading">
    <div v-for="i in rows" :key="i" class="flex items-center gap-3 border-t border-slate-100 px-5 py-4 first:border-t-0 dark:border-white/5">
      <div class="h-10 w-10 flex-shrink-0 animate-pulse rounded-xl bg-base-200 dark:bg-white/5"></div>
      <div class="min-w-0 flex-1 space-y-2">
        <div class="h-3 w-2/3 animate-pulse rounded bg-base-200 dark:bg-white/5"></div>
        <div class="h-2.5 w-1/2 animate-pulse rounded bg-base-200 dark:bg-white/5"></div>
      </div>
      <div class="h-5 w-16 animate-pulse rounded-full bg-base-200 dark:bg-white/5"></div>
    </div>
  </div>
</template>

<script setup>
import * as ui from '@/components/ui/styles'

defineProps({ rows: { type: Number, default: 4 } })
</script>
