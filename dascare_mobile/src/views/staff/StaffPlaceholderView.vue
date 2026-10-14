<template>
  <!-- A section the web portal itself only shows as a placeholder (e.g.
       Scheduled Transport) — same message in the app, so nothing pretends
       to work. -->
  <div class="min-h-screen bg-base-200 dark:bg-[#050e1a]">
    <ScreenHeader :title="route.meta.title" fallback="/staff/more" />
    <div class="px-4 pt-10 text-center">
      <span class="mx-auto grid h-14 w-14 place-items-center rounded-2xl bg-red-50 text-red-600 dark:bg-red-500/10 dark:text-red-300"><Icon :icon="route.meta.icon || 'lucide:construction'" width="26" /></span>
      <h2 class="mt-4 text-lg font-black text-slate-900 dark:text-white">{{ route.meta.title }}</h2>
      <p class="mx-auto mt-2 max-w-xs text-sm leading-6 text-slate-500 dark:text-white/50">{{ route.meta.description }}</p>
      <p class="mx-auto mt-4 max-w-xs rounded-2xl bg-base-100 px-4 py-3 text-xs text-slate-500 dark:bg-white/5 dark:text-white/45">Not available yet — on the web portal too.</p>
    </div>
  </div>
</template>

<script setup>
import { useRoute } from 'vue-router'
import ScreenHeader from '@/components/ScreenHeader.vue'

const route = useRoute()
</script>
