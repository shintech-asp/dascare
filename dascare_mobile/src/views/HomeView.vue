<template>
  <div class="space-y-4 px-4 pt-4">
    <div>
      <p class="text-sm text-slate-500 dark:text-white/45">{{ greeting }},</p>
      <h2 class="text-2xl font-black tracking-tight text-slate-950 dark:text-white">{{ firstName }}</h2>
    </div>

    <KycCard :status="kycStatus" />

    <!-- Primary action — the same siren-red SOS as the web's "Instant rescue" -->
    <RouterLink to="/sos" class="tap relative block overflow-hidden rounded-3xl bg-gradient-to-br from-red-600 to-red-700 p-5 text-white no-underline shadow-lg shadow-red-600/30 dark:shadow-red-950/40">
      <div class="pointer-events-none absolute -right-10 -top-10 h-40 w-40 rounded-full bg-white/10 blur-2xl"></div>
      <div class="relative flex items-center gap-4">
        <span class="relative grid h-16 w-16 flex-shrink-0 place-items-center rounded-full bg-white/15">
          <span class="absolute inset-0 animate-ping rounded-full bg-white/20"></span>
          <Icon icon="lucide:siren" width="30" class="relative" />
        </span>
        <div>
          <p class="text-lg font-black leading-tight">Need an ambulance?</p>
          <p class="mt-1 text-xs leading-5 text-white/85">Tap to send an emergency request with your location.</p>
        </div>
        <Icon icon="lucide:chevron-right" width="22" class="ml-auto flex-shrink-0 text-white/80" />
      </div>
    </RouterLink>

    <section :class="[ui.card, 'p-5']">
      <div class="flex items-center justify-between">
        <h3 class="text-sm font-black text-slate-900 dark:text-white">Active request</h3>
        <span v-if="isVerified" class="text-[0.65rem] font-bold uppercase tracking-wider text-slate-400">Live tracking</span>
      </div>
      <div class="mt-4 rounded-2xl border border-dashed border-slate-300 p-6 text-center dark:border-white/15">
        <Icon icon="lucide:truck" width="22" class="mx-auto text-slate-300 dark:text-white/20" />
        <p class="mt-2 text-xs font-semibold text-slate-400 dark:text-white/35">No active request</p>
        <p class="mt-1 text-[0.68rem] text-slate-400 dark:text-white/25">When you send an SOS, the responding unit shows up here.</p>
      </div>
    </section>

    <div class="flex items-center gap-2.5 rounded-3xl border border-red-200 bg-red-50 p-4 dark:border-red-500/20 dark:bg-red-500/5">
      <Icon icon="lucide:phone-call" width="16" class="flex-shrink-0 text-red-600 dark:text-red-400" />
      <span class="text-xs font-bold text-red-700 dark:text-red-300">Life-threatening? Call your local emergency hotline too.</span>
    </div>
  </div>
</template>

<script setup>
import { computed, onActivated } from 'vue'
import KycCard from '@/components/KycCard.vue'
import * as ui from '@/components/ui/styles'
import { useSession } from '@/composables/useSession'

const { user, kycStatus, isVerified, fetchSession } = useSession()

const firstName = computed(() => (user.value?.name || '').split(' ')[0] || 'there')
const greeting = computed(() => {
  const h = new Date().getHours()
  return h < 12 ? 'Good morning' : h < 18 ? 'Good afternoon' : 'Good evening'
})

// Pick up an ID approval made on the web while the app was open.
onActivated(() => fetchSession())
</script>
