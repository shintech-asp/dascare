<template>
  <PullToRefresh :on-refresh="() => load(true)">
    <div class="space-y-4 px-4 pt-4">
      <div>
        <p class="text-sm text-slate-500 dark:text-white/45">{{ greeting }},</p>
        <h2 class="text-2xl font-black tracking-tight text-slate-950 dark:text-white">{{ firstName }}</h2>
      </div>

      <KycCard :status="kycStatus" />

      <!-- SOS first; when a request is active, its tracking card leads -->
      <SosHero v-if="!active" />

      <!-- Active request (same source as the web dashboard: citizen/dashboard.php) -->
      <RouterLink v-if="active" :to="{ name: 'Track', params: { id: active.id } }" class="tap relative block overflow-hidden rounded-3xl border border-red-200 bg-base-100 p-5 no-underline shadow-sm dark:border-red-500/20 dark:bg-[#071829]">
        <div class="pointer-events-none absolute -right-16 -top-16 h-48 w-48 rounded-full bg-red-500/10 blur-3xl"></div>
        <div class="relative flex items-center justify-between gap-3">
          <span class="flex items-center gap-1.5 text-[0.65rem] font-black uppercase tracking-wider text-red-600 dark:text-red-300">
            <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-red-500"></span> Active request
          </span>
          <span class="rounded-full px-2.5 py-1 text-xs font-bold" :class="statusBadgeClass(active.status)">
            <template v-if="active.merged_into_reference">Linked · </template>{{ statusLabel(active.status) }}
          </span>
        </div>
        <p class="relative mt-3 font-mono text-xs font-semibold text-slate-500 dark:text-white/45">{{ active.reference_number }}</p>
        <p class="relative mt-0.5 truncate text-base font-bold text-slate-900 dark:text-white">{{ active.address_text }}</p>
        <!-- Compact dispatch progress -->
        <div class="relative mt-4 flex items-center gap-1">
          <span v-for="(stage, i) in STAGES" :key="stage.key" class="h-1.5 flex-1 rounded-full" :class="i <= stageIndex(active.status) ? 'bg-red-500' : 'bg-slate-200 dark:bg-white/10'"></span>
        </div>
        <div class="relative mt-3 flex items-center justify-between text-xs">
          <span class="text-slate-500 dark:text-white/45">Submitted {{ relativeTime(active.submitted_at, now) }}</span>
          <span class="flex items-center gap-1 font-bold text-red-600 dark:text-red-300">Track live <Icon icon="lucide:chevron-right" width="14" /></span>
        </div>
      </RouterLink>
      <SosHero v-if="active" compact />

      <div v-if="loading && !active" :class="[ui.card, 'space-y-3 p-5']" aria-busy="true">
        <div class="h-3 w-28 animate-pulse rounded bg-base-200 dark:bg-white/5"></div>
        <div class="h-4 w-3/4 animate-pulse rounded bg-base-200 dark:bg-white/5"></div>
        <div class="h-1.5 w-full animate-pulse rounded-full bg-base-200 dark:bg-white/5"></div>
      </div>

      <!-- Recent requests (history needs a verified ID, like the web) -->
      <section v-if="isVerified && recent.length" :class="[ui.card, 'overflow-hidden']">
        <div class="flex items-center justify-between px-5 pb-2 pt-4">
          <h3 class="text-sm font-black text-slate-900 dark:text-white">Recent requests</h3>
          <RouterLink :to="{ name: 'Requests' }" replace class="py-1 text-xs font-bold text-red-600 no-underline dark:text-red-300">See all</RouterLink>
        </div>
        <RequestRow v-for="r in recent.slice(0, 3)" :key="r.id" :request="r" />
      </section>

    </div>
  </PullToRefresh>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import KycCard from '@/components/KycCard.vue'
import SosHero from '@/components/SosHero.vue'
import PullToRefresh from '@/components/PullToRefresh.vue'
import RequestRow from '@/components/RequestRow.vue'
import * as ui from '@/components/ui/styles'
import api from '@/services/api'
import { useSession } from '@/composables/useSession'
import { useLiveUpdates } from '@/composables/useLiveUpdates'
import { ownRequestChannels } from '@/services/realtime'
import { STAGES, followLinked, isActiveStatus, relativeTime, stageIndex, statusBadgeClass, statusLabel } from '@/utils/requestStatus'

const { user, kycStatus, isVerified, fetchSession } = useSession()

const loading = ref(true)
const active = ref(null)
const recent = ref([])
const now = ref(Date.now())

const firstName = computed(() => (user.value?.name || '').split(' ')[0] || 'there')
const greeting = computed(() => {
  const h = new Date().getHours()
  return h < 12 ? 'Good morning' : h < 18 ? 'Good afternoon' : 'Good evening'
})

async function load(withSession = false) {
  try {
    if (withSession) await fetchSession() // pick up an ID approval made on the web
    if (isVerified.value) {
      const { data } = await api.get('/citizen/dashboard.php')
      active.value = followLinked(data.active_request ?? null)
      recent.value = (data.recent_requests ?? []).map(followLinked)
    } else {
      // The web dashboard is closed to unverified accounts (403), but a
      // citizen who just sent an SOS must still see it: take the newest
      // active one from their own request list instead.
      const { data } = await api.get('/citizen/list.php')
      const list = (Array.isArray(data) ? data : []).map(followLinked)
      active.value = list.find((r) => isActiveStatus(r.status)) ?? null
      recent.value = []
    }
    now.value = Date.now()
  } catch { /* keep showing the last data */ } finally {
    loading.value = false
  }
}

// Live updates on the citizen's requests (GPS pings ignored) + app resume;
// polls every 15 s while live updates are unavailable.
useLiveUpdates(() => load(), { channels: ownRequestChannels, onEvent: (msg) => (msg.name === 'ambulance.location' ? false : undefined) })
onMounted(() => load())
</script>
