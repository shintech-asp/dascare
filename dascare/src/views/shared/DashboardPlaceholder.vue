<template>
  <section class="min-h-screen bg-base-200 pb-12 dark:bg-[#081b2e]">
    <div class="mx-auto max-w-[1450px] px-4 py-7 sm:px-6 lg:px-8">
      <div class="overflow-hidden rounded-[28px] border border-base-300 bg-base-100 shadow-sm dark:border-white/10 dark:bg-[#0d2943]">
        <div class="relative border-b border-base-300 p-6 dark:border-white/10 sm:p-8">
          <div class="pointer-events-none absolute right-0 top-0 h-40 w-40 rounded-full bg-red-500/10 blur-3xl"></div>
          <div class="relative flex flex-col gap-5 sm:flex-row sm:items-start sm:justify-between">
            <div class="max-w-3xl">
              <span class="inline-flex items-center gap-2 rounded-full bg-red-50 px-3 py-1 text-[0.68rem] font-black uppercase tracking-[0.12em] text-red-700 dark:bg-red-500/10 dark:text-red-300">
                <Icon :icon="route.meta.icon || 'lucide:panels-top-left'" width="14" />
                {{ route.meta.section || 'Module' }}
              </span>
              <h1 class="mt-4 text-3xl font-black tracking-tight text-slate-950 dark:text-white">{{ route.meta.title }}</h1>
              <p class="mt-3 text-sm leading-6 text-slate-500 dark:text-white/50 sm:text-base">{{ route.meta.description }}</p>
            </div>
            <span class="inline-flex w-fit items-center gap-2 rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs font-bold text-amber-700 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-300">
              <Icon icon="lucide:layers-3" width="15" /> Planned extension
            </span>
          </div>
        </div>

        <div class="grid gap-4 p-6 sm:grid-cols-2 sm:p-8 xl:grid-cols-3">
          <article v-for="feature in features" :key="feature.title" class="rounded-2xl border border-base-300 bg-base-200/70 p-5 dark:border-white/10 dark:bg-base-100/[0.03]">
            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-base-100 text-red-600 shadow-sm dark:bg-base-100/5 dark:text-red-300">
              <Icon :icon="feature.icon" width="19" />
            </div>
            <h2 class="mt-4 text-sm font-black text-slate-900 dark:text-white">{{ feature.title }}</h2>
            <p class="mt-2 text-xs leading-5 text-slate-500 dark:text-white/40">{{ feature.description }}</p>
          </article>
        </div>

        <div class="border-t border-base-300 bg-base-200/70 px-6 py-5 dark:border-white/10 dark:bg-base-100/[0.02] sm:px-8">
          <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-3">
              <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-emerald-100 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-300">
                <Icon icon="lucide:check-check" width="18" />
              </span>
              <div>
                <p class="text-sm font-bold text-slate-800 dark:text-white/75">Workflow boundary documented</p>
                <p class="text-xs text-slate-400 dark:text-white/35">This extension is intentionally outside the completed demo workflow. The current build keeps the route and permission boundary visible without presenting fabricated records or actions.</p>
              </div>
            </div>
            <RouterLink :to="parentPath" class="inline-flex items-center justify-center gap-2 rounded-xl border border-base-300 px-4 py-2.5 text-xs font-bold text-slate-600 no-underline transition-colors hover:bg-base-100 dark:border-white/10 dark:text-white/55 dark:hover:bg-base-100/5">
              <Icon icon="lucide:arrow-left" width="15" /> Back to overview
            </RouterLink>
          </div>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup>
import { computed } from 'vue'
import { useRoute } from 'vue-router'

const route = useRoute()
const parentPath = computed(() => route.path.split('/').slice(0, 2).join('/') || '/')
const features = computed(() => route.meta.features || [
  { title: 'Current scope', icon: 'lucide:database', description: 'This extension is documented but is not required for the completed emergency-rescue demonstration.' },
  { title: 'Access controls', icon: 'lucide:shield-check', description: 'The route already follows DASCARE role and permission boundaries.' },
  { title: 'Implementation boundary', icon: 'lucide:history', description: 'No fake records or actions are shown for functionality that has not been implemented yet.' },
])
</script>
