<template>
  <!-- Same lockup as the web header (dascare/src/components/AppHeader.vue):
       logo tile, "Dascare", blue AMBULANCE DISPATCH, location line. -->
  <div class="flex items-center gap-3">
    <span class="grid flex-shrink-0 place-items-center rounded-2xl bg-white shadow-sm ring-1 ring-[#1976D2]/20 dark:bg-white/95" :class="compact ? 'h-10 w-10' : 'h-14 w-14'">
      <img src="/img/logoo.png" alt="" class="object-contain" :class="compact ? 'h-8 w-8' : 'h-11 w-11'" />
    </span>
    <span class="flex flex-col leading-tight">
      <span class="font-bold text-slate-900 dark:text-white" :class="compact ? 'text-lg' : 'text-2xl'">Dascare</span>
      <span class="font-semibold uppercase text-[#1976D2] dark:text-[#7fb3ec]" :class="compact ? 'text-[8px] tracking-[0.28em]' : 'text-[10px] tracking-[0.3em]'">Ambulance Dispatch</span>
      <span v-if="!compact" class="mt-0.5 flex items-center gap-1 text-[10px] text-slate-400 dark:text-white/40">
        <Icon icon="lucide:map-pin" width="11" /> Dasmariñas City, Cavite
      </span>
    </span>
  </div>
</template>

<script setup>
defineProps({ compact: { type: Boolean, default: false } })
</script>
