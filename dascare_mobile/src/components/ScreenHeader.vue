<template>
  <!-- Top bar for stacked (non-tab) screens: back arrow + title. Sits under
       the status bar/notch and stays put while the content scrolls. -->
  <header class="sticky top-0 z-30 border-b border-base-300/70 bg-base-200/90 pt-[var(--safe-top)] backdrop-blur dark:border-white/10 dark:bg-[#050e1a]/90">
    <div class="flex h-14 items-center gap-1 px-2">
      <button type="button" class="tap grid h-11 w-11 place-items-center rounded-xl text-slate-700 dark:text-white/80" aria-label="Back" @click="goBack">
        <Icon icon="lucide:arrow-left" width="22" />
      </button>
      <h1 class="min-w-0 flex-1 truncate text-base font-black text-slate-900 dark:text-white">{{ title }}</h1>
      <slot name="actions" />
    </div>
  </header>
</template>

<script setup>
import { useRouter } from 'vue-router'

const props = defineProps({
  title: { type: String, default: '' },
  fallback: { type: String, default: '/' },
})
const router = useRouter()

function goBack() {
  if (window.history.state?.back) router.back()
  else router.replace(props.fallback)
}
</script>
