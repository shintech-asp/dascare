<template>
  <main class="relative flex min-h-screen flex-col overflow-hidden bg-base-200 px-5 pb-[calc(var(--safe-bottom)+1.5rem)] pt-[calc(var(--safe-top)+1.25rem)] dark:bg-[#050e1a]">
    <!-- Soft siren glows — same motif as the web's AuthPage / Hero -->
    <div class="pointer-events-none absolute -left-24 top-24 h-72 w-72 rounded-full bg-[#1976D2]/15 blur-3xl"></div>
    <div class="pointer-events-none absolute -right-24 top-64 h-72 w-72 rounded-full bg-red-500/15 blur-3xl"></div>

    <header class="relative flex items-center justify-between">
      <BrandLockup />
      <button type="button" class="tap grid h-10 w-10 place-items-center rounded-xl text-amber-500 dark:text-[#7fb3ec]" aria-label="Toggle theme" @click="toggleTheme">
        <Icon :icon="theme === 'dark' ? 'lucide:moon' : 'lucide:sun'" width="22" />
      </button>
    </header>

    <section class="relative mt-10">
      <div class="inline-flex items-center gap-2.5 border-l-2 border-red-600 pl-3 dark:border-red-400">
        <span class="font-mono text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-500 dark:text-white/50">Dasmariñas · Ambulance rescue</span>
      </div>
      <h1 class="mt-3 text-[2rem] font-black leading-[1.08] tracking-tight text-slate-950 dark:text-white">
        Help is one tap away. Every step of the <span class="text-red-600 dark:text-red-400">response.</span>
      </h1>
      <p class="mt-3 text-sm leading-6 text-slate-600 dark:text-[#a9c6e8]">
        Request an ambulance, share your exact location, and follow the responding unit until help arrives.
      </p>
    </section>

    <!-- Guest SOS — same card as the web login page's "Need help right now?" -->
    <section class="relative mt-7 rounded-3xl border-2 border-dashed border-red-200 bg-red-50/70 p-5 dark:border-red-500/25 dark:bg-red-500/5">
      <p class="flex items-center gap-1.5 text-xs font-bold uppercase tracking-wide text-red-700 dark:text-red-300">
        <Icon icon="lucide:siren" width="15" /> Need help right now?
      </p>
      <p class="mt-1 text-xs leading-relaxed text-slate-500 dark:text-white/45">
        Don't wait to sign in — send an emergency request as a guest, no account required.
      </p>
      <RouterLink to="/sos" class="tap relative mt-4 flex w-full items-center justify-center gap-2.5 overflow-hidden rounded-2xl bg-red-600 py-4 text-base font-black text-white no-underline shadow-lg shadow-red-600/30 dark:shadow-red-950/40">
        <span class="absolute inset-0 animate-pulse bg-white/10"></span>
        <Icon icon="lucide:siren" width="22" class="relative" />
        <span class="relative">Emergency SOS</span>
      </RouterLink>
    </section>

    <!-- Guest SOS requests sent from this phone (keys saved by the SOS screen) -->
    <section v-if="guestRequests.length" class="relative mt-4 overflow-hidden rounded-3xl border border-base-300 bg-base-100 dark:border-white/10 dark:bg-[#071829]">
      <p class="px-5 pb-1 pt-4 text-xs font-black uppercase tracking-wider text-slate-500 dark:text-white/45">Your emergency requests</p>
      <RouterLink v-for="r in guestRequests.slice(0, 3)" :key="r.id" :to="{ name: 'Track', params: { id: r.id } }" class="tap flex items-center gap-3 border-t border-slate-100 px-5 py-3.5 no-underline first-of-type:border-t-0 dark:border-white/5">
        <span class="relative grid h-9 w-9 flex-shrink-0 place-items-center rounded-xl bg-red-50 dark:bg-red-500/10">
          <span v-if="isActiveStatus(statuses[r.id])" class="absolute inset-0 animate-ping rounded-xl bg-red-500/15 [animation-duration:2.2s]"></span>
          <Icon icon="lucide:siren" width="16" class="relative text-red-600 dark:text-red-300" />
        </span>
        <span class="min-w-0 flex-1">
          <span class="block font-mono text-xs font-semibold text-slate-700 dark:text-white/75">{{ r.reference_number }}</span>
          <span class="block text-[0.7rem] text-slate-400 dark:text-white/35">{{ relativeTime(r.created_at) }}</span>
        </span>
        <span v-if="statuses[r.id]" class="rounded-full px-2.5 py-1 text-[0.65rem] font-bold" :class="statusBadgeClass(statuses[r.id])">{{ statusLabel(statuses[r.id]) }}</span>
        <Icon icon="lucide:chevron-right" width="16" class="flex-shrink-0 text-slate-300 dark:text-white/20" />
      </RouterLink>
    </section>

    <section class="relative mt-auto space-y-3 pt-8">
      <RouterLink to="/login" class="tap flex w-full items-center justify-center rounded-2xl bg-slate-900 py-4 font-bold text-white no-underline dark:bg-white dark:text-slate-900">
        Log in
      </RouterLink>
      <RouterLink to="/register" class="tap flex w-full items-center justify-center rounded-2xl border-2 border-slate-200 py-3.5 font-bold text-slate-700 no-underline dark:border-white/15 dark:text-white/80">
        Create an account
      </RouterLink>
      <p class="pt-1 text-center text-[11px] leading-5 text-slate-400 dark:text-white/35">
        An account lets you track requests and keep your emergency medical info on file.
      </p>
      <RouterLink to="/diagnostics" class="block text-center text-[11px] font-semibold text-slate-400 no-underline dark:text-white/30">
        <Icon icon="lucide:server" width="11" class="mr-0.5 inline" /> Server connection
      </RouterLink>
    </section>
  </main>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import BrandLockup from '@/components/BrandLockup.vue'
import api from '@/services/api'
import { useTheme } from '@/composables/useTheme'
import { useGuestKeys } from '@/composables/useGuestKeys'
import { isActiveStatus, relativeTime, statusBadgeClass, statusLabel } from '@/utils/requestStatus'

const { theme, toggleTheme } = useTheme()
const { requests: guestRequests, load: loadGuestKeys } = useGuestKeys()
const statuses = ref({})

// Live status for the latest guest requests (detail.php accepts the guest key).
onMounted(async () => {
  await loadGuestKeys()
  await Promise.all(guestRequests.value.slice(0, 3).map(async (r) => {
    try {
      const { data } = await api.get('/citizen/detail.php', { params: { id: r.id } })
      statuses.value[r.id] = data.merged_into?.status ?? data.status
    } catch { /* offline — show without a status */ }
  }))
})
</script>
