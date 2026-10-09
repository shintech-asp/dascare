<template>
  <span
    class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[.68rem] font-black"
    :class="live
      ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300'
      : 'bg-base-200 text-slate-500 dark:bg-white/5 dark:text-white/45'"
    :title="live ? 'Updates arrive the moment they happen.' : `Live updates unavailable — refreshing every ${fallbackSeconds} seconds.`"
  >
    <span class="relative flex h-1.5 w-1.5">
      <span v-if="live" class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-500 opacity-75"></span>
      <span class="relative inline-flex h-1.5 w-1.5 rounded-full" :class="live ? 'bg-emerald-500' : 'bg-slate-400 dark:bg-white/30'"></span>
    </span>
    {{ live ? 'Live' : `Every ${fallbackSeconds}s` }}
  </span>
</template>

<script setup>
// Shows whether a screen is on live updates (useLiveUpdates().live) or polling.
defineProps({
  live: { type: Boolean, default: false },
  fallbackSeconds: { type: Number, default: 15 },
})
</script>
