<template>
  <div class="space-y-4 px-4 pt-4">
    <!-- Same rule as the web (router requiresKyc): history unlocks once the
         ID is approved. SOS is never locked. -->
    <template v-if="!isVerified">
      <KycCard :status="kycStatus" feature="your request history and live tracking" />
      <div class="rounded-3xl border border-dashed border-slate-300 px-6 py-12 text-center dark:border-white/15">
        <Icon icon="lucide:lock-keyhole" width="26" class="mx-auto text-slate-300 dark:text-white/20" />
        <p class="mt-3 text-sm font-semibold text-slate-500 dark:text-white/40">Request history is locked</p>
        <p class="mt-1 text-xs text-slate-400 dark:text-white/30">Verify your identity to see past requests here.</p>
      </div>
    </template>
    <div v-else class="rounded-3xl border border-dashed border-slate-300 px-6 py-14 text-center dark:border-white/15">
      <Icon icon="lucide:clipboard-list" width="28" class="mx-auto text-slate-300 dark:text-white/20" />
      <p class="mt-3 text-sm font-semibold text-slate-500 dark:text-white/40">No requests yet</p>
      <p class="mt-1 text-xs text-slate-400 dark:text-white/30">Emergency requests you send will be listed here with their status.</p>
    </div>
  </div>
</template>

<script setup>
import KycCard from '@/components/KycCard.vue'
import { useSession } from '@/composables/useSession'

const { kycStatus, isVerified } = useSession()
</script>
