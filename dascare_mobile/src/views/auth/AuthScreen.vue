<template>
  <!-- Frame for the account screens: back bar, then the same card the web
       AuthPage uses (cream brand strip with "log in"/"sign up" label). -->
  <div class="min-h-screen bg-base-200 dark:bg-[#050e1a]">
    <ScreenHeader :title="''" :fallback="fallback" />
    <main class="px-4 pb-[calc(var(--safe-bottom)+2rem)] pt-2">
      <div class="overflow-hidden rounded-3xl border border-slate-200/80 bg-base-100 shadow-xl shadow-slate-300/40 dark:border-white/10 dark:bg-[#071829] dark:shadow-black/40">
        <div class="flex items-center justify-between border-b border-slate-200 bg-[#f8f3e8] px-6 py-4 dark:border-white/10 dark:bg-white/[0.03]">
          <BrandLockup compact />
          <span class="font-mono text-[11px] uppercase tracking-[0.2em] text-slate-400 dark:text-white/40">{{ tag }}</span>
        </div>
        <div class="px-6 py-7">
          <h2 class="text-2xl font-black tracking-tight text-slate-950 dark:text-white">{{ title }}</h2>
          <p v-if="subtitle" class="mt-1 text-sm text-slate-500 dark:text-[#a9c6e8]">{{ subtitle }}</p>
          <slot />
        </div>
      </div>
      <slot name="after" />
    </main>
  </div>
</template>

<script setup>
import BrandLockup from '@/components/BrandLockup.vue'
import ScreenHeader from '@/components/ScreenHeader.vue'

defineProps({
  title: { type: String, required: true },
  subtitle: { type: String, default: '' },
  tag: { type: String, default: 'log in' },
  fallback: { type: String, default: '/welcome' },
})
</script>
