<template>
  <PullToRefresh :on-refresh="() => load(true)">
    <div class="space-y-4 px-4 pt-4">
      <div>
        <p class="text-sm text-slate-500 dark:text-white/45">{{ greeting }},</p>
        <h2 class="text-2xl font-black tracking-tight text-slate-950 dark:text-white">{{ firstName }}</h2>
      </div>

      <KycCard :status="kycStatus" />

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

      <!-- Primary action -->
      <RouterLink to="/sos" class="tap relative block overflow-hidden rounded-3xl bg-gradient-to-br from-red-600 to-red-700 p-5 text-white no-underline shadow-lg shadow-red-600/30 dark:shadow-red-950/40">
        <div class="pointer-events-none absolute -right-10 -top-10 h-40 w-40 rounded-full bg-white/10 blur-2xl"></div>
        <div class="relative flex items-center gap-4">
          <span class="relative grid h-16 w-16 flex-shrink-0 place-items-center rounded-full bg-white/15">
            <span class="absolute inset-0 animate-ping rounded-full bg-white/20"></span>
            <Icon icon="lucide:siren" width="30" class="relative" />
          </span>
          <div>
            <p class="text-lg font-black leading-tight">{{ active ? 'Another emergency?' : 'Need an ambulance?' }}</p>
            <p class="mt-1 text-xs leading-5 text-white/85">Tap to send an emergency request with your location.</p>
          </div>
          <Icon icon="lucide:chevron-right" width="22" class="ml-auto flex-shrink-0 text-white/80" />
        </div>
      </RouterLink>

      <div v-if="loading && !active" :class="[ui.card, 'space-y-3 p-5']" aria-busy="true">
        <div class="h-3 w-28 animate-pulse rounded bg-base-200 dark:bg-white/5"></div>
        <div class="h-4 w-3/4 animate-pulse rounded bg-base-200 dark:bg-white/5"></div>
        <div class="h-1.5 w-full animate-pulse rounded-full bg-base-200 dark:bg-white/5"></div>
      </div>
      <section v-else-if="!active" :class="[ui.card, 'p-5']">
        <h3 class="text-sm font-black text-slate-900 dark:text-white">Active request</h3>
        <div class="mt-4 rounded-2xl border border-dashed border-slate-300 p-6 text-center dark:border-white/15">
          <Icon icon="lucide:truck" width="22" class="mx-auto text-slate-300 dark:text-white/20" />
          <p class="mt-2 text-xs font-semibold text-slate-400 dark:text-white/35">{{ loading ? 'Checking…' : 'No active request' }}</p>
          <p class="mt-1 text-[0.68rem] text-slate-400 dark:text-white/25">When you send an SOS, the responding unit shows up here.</p>
        </div>
      </section>

      <!-- Recent requests (history needs a verified ID, like the web) -->
      <section v-if="isVerified && recent.length" :class="[ui.card, 'overflow-hidden']">
        <div class="flex items-center justify-between px-5 pb-2 pt-4">
          <h3 class="text-sm font-black text-slate-900 dark:text-white">Recent requests</h3>
          <RouterLink :to="{ name: 'Requests' }" replace class="py-1 text-xs font-bold text-red-600 no-underline dark:text-red-300">See all</RouterLink>
        </div>
        <RequestRow v-for="r in recent.slice(0, 3)" :key="r.id" :request="r" />
      </section>

      <a href="tel:911" class="tap flex items-center gap-2.5 rounded-3xl border border-red-200 bg-red-50 p-4 no-underline dark:border-red-500/20 dark:bg-red-500/5">
        <Icon icon="lucide:phone-call" width="16" class="flex-shrink-0 text-red-600 dark:text-red-400" />
        <span class="text-xs font-bold text-red-700 dark:text-red-300">Life-threatening? Call your local emergency hotline too.</span>
      </a>
    </div>
  </PullToRefresh>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import KycCard from '@/components/KycCard.vue'
import PullToRefresh from '@/components/PullToRefresh.vue'
import RequestRow from '@/components/RequestRow.vue'
import * as ui from '@/components/ui/styles'
import api from '@/services/api'
import { useSession } from '@/composables/useSession'
import { useLiveRefresh } from '@/composables/useLiveRefresh'
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

useLiveRefresh(() => load()) // polling + app resume (App.vue refreshes the session on resume)
onMounted(() => load())
</script>
