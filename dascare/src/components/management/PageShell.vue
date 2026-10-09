<template>
  <section class="min-h-screen bg-base-200 pb-12 dark:bg-[#050e1a]">
    <section class="relative overflow-hidden border-b border-base-300 bg-base-100 dark:border-white/10 dark:bg-[#071829]">
      <div class="pointer-events-none absolute inset-0 opacity-70 dark:opacity-30" style="background:radial-gradient(ellipse at top left,rgba(220,38,38,.06),transparent 55%)"></div>
      <div class="relative mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
        <div class="flex flex-wrap items-end justify-between gap-4">
          <div>
            <span class="inline-flex items-center gap-2 rounded-full bg-red-50 px-3 py-1 font-mono text-[.65rem] font-semibold uppercase tracking-[.15em] text-red-700 dark:bg-red-500/10 dark:text-red-300"><Icon :icon="icon" width="14" />{{ eyebrow }}</span>
            <h1 class="mt-2 text-2xl font-black tracking-tight text-slate-900 dark:text-white">{{ title }}</h1>
            <p class="mt-1 max-w-3xl text-sm text-slate-500 dark:text-white/45">{{ subtitle }}</p>
          </div>
          <slot name="actions" />
        </div>
      </div>
    </section>
    <div class="mx-auto max-w-7xl px-4 pt-6 sm:px-6 lg:px-8"><slot /></div>
  </section>
</template>
<script setup>
import { Icon } from '@iconify/vue'
defineProps({title:{type:String,required:true},subtitle:{type:String,default:''},icon:{type:String,default:'lucide:panels-top-left'},eyebrow:{type:String,default:'DASCARE Management'}})
</script>
