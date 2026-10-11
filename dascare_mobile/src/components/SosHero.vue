<template>
  <!-- Home's main action: a big round SOS button, like an emergency app.
       Compact when a request is already active (its tracking card comes first). -->
  <section class="flex flex-col items-center" :class="compact ? 'py-2' : 'pb-2 pt-4'">
    <RouterLink to="/sos" aria-label="Send an emergency request" class="tap relative grid place-items-center rounded-full no-underline"
      :class="compact ? 'h-28 w-28' : 'h-48 w-48'" @click="buzz">
      <span class="absolute -inset-4 rounded-full bg-red-500/[0.07] dark:bg-red-500/10"></span>
      <span class="absolute inset-0 animate-ping rounded-full bg-red-500/20 [animation-duration:2.4s]"></span>
      <span class="absolute inset-0 rounded-full bg-gradient-to-br from-red-500 to-red-700 shadow-2xl shadow-red-600/40 ring-8 ring-red-100 dark:shadow-red-950/50 dark:ring-red-500/15"></span>
      <span class="relative flex flex-col items-center text-white">
        <Icon icon="lucide:siren" :width="compact ? 28 : 46" />
        <span class="mt-1 font-black tracking-[0.2em]" :class="compact ? 'text-lg' : 'text-3xl'">SOS</span>
      </span>
    </RouterLink>
    <p class="font-black text-slate-900 dark:text-white" :class="compact ? 'mt-4 text-sm' : 'mt-6 text-lg'">{{ compact ? 'Another emergency?' : 'Need an ambulance?' }}</p>
    <p class="mt-1 max-w-[17rem] text-center text-xs leading-5 text-slate-500 dark:text-white/45">Tap SOS — your location goes straight to dispatch. No typing needed.</p>
  </section>
</template>

<script setup>
import { Haptics, ImpactStyle } from '@capacitor/haptics'

defineProps({ compact: { type: Boolean, default: false } })

function buzz() { Haptics.impact({ style: ImpactStyle.Medium }).catch(() => {}) }
</script>
