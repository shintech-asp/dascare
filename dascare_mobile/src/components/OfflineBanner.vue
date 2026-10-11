<template>
  <!-- Shown on every screen while the phone has no internet: an SOS can't
       reach dispatch until the connection is back. -->
  <Transition name="offline">
    <div v-if="!online" class="fixed inset-x-0 top-0 z-[90] bg-red-700 pt-[var(--safe-top)] text-white shadow-lg dark:bg-red-800" role="alert">
      <div class="flex items-center gap-3 px-4 py-2.5">
        <Icon icon="lucide:wifi-off" width="18" class="flex-shrink-0" />
        <p class="min-w-0 flex-1 text-xs font-bold leading-snug">
          No internet connection.
          <span class="block font-semibold text-white/80">DASCARE can't reach dispatch until you're back online.</span>
        </p>
      </div>
    </div>
  </Transition>
</template>

<script setup>
import { useNetwork } from '@/composables/useNetwork'

const { online } = useNetwork()
</script>

<style scoped>
.offline-enter-active,
.offline-leave-active { transition: transform 0.25s ease; }
.offline-enter-from,
.offline-leave-to { transform: translateY(-100%); }
</style>
